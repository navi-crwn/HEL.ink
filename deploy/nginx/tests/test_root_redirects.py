#!/usr/bin/env python3
"""Source and isolated-loopback regression tests for the hel.ink root redirects."""

import http.client
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
from pathlib import Path

TESTS_DIR = Path(__file__).resolve().parent
SOURCE = TESTS_DIR.parent / "hel.ink.conf"
ROOTS = {"/l": "link", "/l/": "link", "/b": "bio", "/b/" : "bio"}
QUERIES = (None, "x=1&y=%2F", "dup=1&dup=1&next=a%2Bb%3Fx%3D1")


def location_block(source, path):
    match = re.search(
        r"^    location = " + re.escape(path) + r"\s*\{.*?^    \}",
        source,
        re.MULTILINE | re.DOTALL,
    )
    if not match:
        raise AssertionError(f"missing exact location {path}")
    return match.group(0)


class SourceContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = SOURCE.read_text(encoding="utf-8")

    def test_exact_root_redirects_and_method_limits(self):
        for path, app in ROOTS.items():
            with self.subTest(path=path):
                block = location_block(self.source, path)
                expected = f"return 301 https://{app}.hel.ink/$is_args$args;"
                self.assertEqual(block.count(expected), 1)
                self.assertIn("if ($request_method !~ ^(GET|HEAD)$) { return 405; }", block)
                self.assertIn("add_header Allow \"GET, HEAD\" always;", block)
                self.assertIn("return 405;", block)

    def test_existing_application_routing_is_preserved(self):
        for directive in (
            "location ^~ /l/",
            "location ^~ /b/",
            'location ~ "^/([A-Za-z0-9-]{1,100})$"',
            "return 301 /l/$1;",
            "root /var/www/helink/linkhop/public;",
            "root /var/www/helink/biohop/public;",
            "fastcgi_pass unix:/run/php/php8.3-fpm.sock;",
        ):
            self.assertIn(directive, self.source)
        self.assertEqual(
            len(re.findall(r"^            fastcgi_pass unix:/run/php/php8\.3-fpm\.sock;", self.source, re.MULTILINE)),
            2,
        )


@unittest.skipUnless(shutil.which("nginx"), "nginx is unavailable; source checks are not HTTP proof")
class LoopbackContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix=".u05-", dir=TESTS_DIR)
        runtime = Path(cls.temp.name)
        cls.port = cls.free_port()
        source = SOURCE.read_text(encoding="utf-8")
        exact_locations = "\n\n".join(location_block(source, path) for path in ROOTS)
        config = f"""pid {runtime}/nginx.pid;
error_log stderr notice;
events {{}}
http {{
    access_log off;
    default_type application/octet-stream;
    server {{
        listen 127.0.0.1:{cls.port};
        server_name _;
{exact_locations}
        location ^~ /l/ {{ return 200 'linkhop-slug'; }}
        location ^~ /b/ {{ return 200 'biohop-slug'; }}
        location ~ "^/([A-Za-z0-9-]{{1,100}})$" {{ return 301 /l/$1; }}
        location / {{ return 200 'hub'; }}
    }}
}}
"""
        cls.config = runtime / "nginx.conf"
        cls.config.write_text(config, encoding="utf-8")
        validated = subprocess.run(
            ["nginx", "-p", str(runtime), "-c", str(cls.config), "-t"],
            check=False,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
        if validated.returncode:
            raise RuntimeError(f"isolated nginx validation failed:\n{validated.stderr}")
        cls.log = (runtime / "process.log").open("w", encoding="utf-8")
        cls.process = subprocess.Popen(
            ["nginx", "-p", str(runtime), "-c", str(cls.config), "-g", "daemon off; master_process off;"],
            stdin=subprocess.DEVNULL,
            stdout=cls.log,
            stderr=subprocess.STDOUT,
        )
        deadline = time.monotonic() + 5
        while time.monotonic() < deadline:
            if cls.process.poll() is not None:
                raise RuntimeError("isolated nginx fixture exited before accepting requests")
            try:
                with socket.create_connection(("127.0.0.1", cls.port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.02)
        else:
            raise RuntimeError("isolated nginx fixture did not become ready")

    @classmethod
    def tearDownClass(cls):
        if hasattr(cls, "process") and cls.process.poll() is None:
            cls.process.terminate()
            try:
                cls.process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                cls.process.kill()
                cls.process.wait(timeout=5)
        if hasattr(cls, "log"):
            cls.log.close()
        if hasattr(cls, "temp"):
            cls.temp.cleanup()

    @staticmethod
    def free_port():
        for port in range(8130, 8200):
            with socket.socket() as sock:
                try:
                    sock.bind(("127.0.0.1", port))
                    return port
                except OSError:
                    pass
        raise RuntimeError("no permitted loopback port is available")

    @classmethod
    def request(cls, method, target):
        connection = http.client.HTTPConnection("127.0.0.1", cls.port, timeout=2)
        try:
            connection.request(method, target)
            response = connection.getresponse()
            body = response.read()
            return response.status, response.getheaders(), body
        finally:
            connection.close()

    def test_get_and_head_redirect_with_exact_query(self):
        for path, app in ROOTS.items():
            for method in ("GET", "HEAD"):
                for query in QUERIES:
                    target = path + (f"?{query}" if query is not None else "")
                    with self.subTest(method=method, target=target):
                        status, headers, body = self.request(method, target)
                        expected = f"https://{app}.hel.ink/" + (f"?{query}" if query else "")
                        self.assertEqual(status, 301)
                        self.assertEqual(dict(headers).get("Location"), expected)
                        if method == "HEAD":
                            self.assertEqual(body, b"")

    def test_other_methods_are_405_without_redirect_or_fallback(self):
        for path in ROOTS:
            for method in ("POST", "PUT", "PATCH", "DELETE", "OPTIONS"):
                with self.subTest(method=method, path=path):
                    status, headers, _ = self.request(method, path + "?dup=1&dup=1")
                    header_map = dict(headers)
                    self.assertEqual(status, 405)
                    self.assertEqual(header_map.get("Allow"), "GET, HEAD")
                    self.assertNotIn("Location", header_map)

    def test_slug_and_legacy_routes_still_take_their_handlers(self):
        for method, path, marker in (
            ("GET", "/l/example", b"linkhop-slug"),
            ("POST", "/l/example", b"linkhop-slug"),
            ("GET", "/b/example", b"biohop-slug"),
            ("POST", "/b/example", b"biohop-slug"),
        ):
            with self.subTest(method=method, path=path):
                status, _, body = self.request(method, path)
                self.assertEqual((status, body), (200, marker))
        status, headers, _ = self.request("GET", "/legacy-slug?x=%2F&x=2")
        self.assertEqual(status, 301)
        self.assertEqual(
            dict(headers).get("Location"),
            f"http://127.0.0.1:{self.port}/l/legacy-slug",
        )


if __name__ == "__main__":
    unittest.main(verbosity=2)
