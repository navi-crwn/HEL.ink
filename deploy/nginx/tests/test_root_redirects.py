#!/usr/bin/env python3
"""Source and isolated-loopback regression tests for the hel.ink root redirects and hub map."""

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
REPO_ROOT = TESTS_DIR.parents[1]
SOURCE = TESTS_DIR.parent / "hel.ink.conf"
ROOTS = {"/l": "link", "/l/": "link", "/b": "bio", "/b/": "bio"}
QUERIES = (None, "x=1&y=%2F", "dup=1&dup=1&next=a%2Bb%3Fx%3D1")

# Single-segment public hub paths that must be served by the hub and NEVER redirect to /l/<slug>
HUB_SINGLE_SEGMENT_PAGES = (
    "about",
    "announcements",
    "apps",
    "contact",
    "dashboard",
    "faq",
    "goodbye",
    "help",
    "how-it-works",
    "login",
    "onboarding",
    "open-source",
    "plans",
    "privacy",
    "products",
    "profile",
    "register",
    "security",
    "settings",
    "signup",
    "status",
    "status.json",
    "terms",
    "tos",
    "up",
)


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
                self.assertIn('if ($request_method !~ ^(GET|HEAD)$) { return 405; }', block)
                self.assertIn('add_header Allow "GET, HEAD" always;', block)
                self.assertIn("return 405;", block)

    def test_existing_application_routing_is_preserved(self):
        for directive in (
            "map $uri $hop_slug",
            "map $uri $hop_hub_page",
            'map "$hop_hub_page:$hop_slug" $hop_legacy_redirect',
            "if ($hop_legacy_redirect) {",
            "return 301 /l/$hop_legacy_redirect;",
            "location ^~ /l/",
            "location ^~ /b/",
            "root /var/www/helink/linkhop/public;",
            "root /var/www/helink/biohop/public;",
            "fastcgi_pass unix:/run/php/php8.3-fpm.sock;",
        ):
            with self.subTest(directive=directive):
                self.assertIn(directive, self.source)
        self.assertGreaterEqual(
            len(re.findall(r"fastcgi_pass unix:/run/php/php8\.3-fpm\.sock;", self.source)),
            2,
        )

    def test_all_single_segment_hub_pages_in_map(self):
        map_match = re.search(r'map \$uri \$hop_hub_page\s*\{(.*?)\}', self.source, re.DOTALL)
        self.assertIsNotNone(map_match, "map $uri $hop_hub_page not found in source")
        map_body = map_match.group(1)
        # Extract regex list between "~^/(" and ")$"
        regex_match = re.search(r'"~\^\/\((.*?)\)\$"\s+1;', map_body)
        self.assertIsNotNone(regex_match, "regex pattern not found in map $uri $hop_hub_page")
        items = set(regex_match.group(1).replace(r"\.", ".").split("|"))
        for page in HUB_SINGLE_SEGMENT_PAGES:
            with self.subTest(page=page):
                self.assertIn(page, items, f"Hub page '{page}' is missing from map $uri $hop_hub_page")


@unittest.skipUnless(shutil.which("nginx"), "nginx is unavailable; source checks are not HTTP proof")
class LoopbackContract(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix=".u05-", dir=TESTS_DIR)
        runtime = Path(cls.temp.name)
        cls.port = cls.free_port()
        source = SOURCE.read_text(encoding="utf-8")
        exact_locations = "\n\n".join(location_block(source, path) for path in ROOTS)

        # Extract the maps from source
        slug_map = re.search(r"map \$uri \$hop_slug\s*\{.*?\n\}", source, re.DOTALL).group(0)
        hub_map = re.search(r"map \$uri \$hop_hub_page\s*\{.*?\n\}", source, re.DOTALL).group(0)
        redir_map = re.search(r'map "\$hop_hub_page:\$hop_slug" \$hop_legacy_redirect\s*\{.*?\n\}', source, re.DOTALL).group(0)

        config = f"""pid {runtime}/nginx.pid;
error_log stderr notice;
events {{}}
http {{
    access_log off;
    default_type application/octet-stream;
    {slug_map}
    {hub_map}
    {redir_map}
    server {{
        listen 127.0.0.1:{cls.port};
        server_name _;
{exact_locations}
        location ^~ /l/ {{ return 200 'linkhop-slug'; }}
        location ^~ /b/ {{ return 200 'biohop-slug'; }}
        location / {{
            if ($hop_legacy_redirect) {{
                return 301 /l/$hop_legacy_redirect;
            }}
            return 200 'hub';
        }}
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
        if hasattr(cls, "process"):
            cls.process.terminate()
            cls.process.wait(timeout=5)
        if hasattr(cls, "log"):
            cls.log.close()
        if hasattr(cls, "temp"):
            cls.temp.cleanup()

    @staticmethod
    def free_port():
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as probe:
            probe.bind(("127.0.0.1", 0))
            return probe.getsockname()[1]

    def request(self, method, path):
        conn = http.client.HTTPConnection("127.0.0.1", self.port, timeout=2)
        try:
            conn.request(method, path)
            response = conn.getresponse()
            body = response.read()
            return response.status, dict(response.getheaders()), body
        finally:
            conn.close()

    def test_get_and_head_redirect_with_exact_query(self):
        for path, app in ROOTS.items():
            for query in QUERIES:
                target = path if query is None else f"{path}?{query}"
                expected_location = f"https://{app}.hel.ink/" if query is None else f"https://{app}.hel.ink/?{query}"
                for method in ("GET", "HEAD"):
                    with self.subTest(method=method, target=target):
                        status, headers, body = self.request(method, target)
                        self.assertEqual(status, 301)
                        self.assertEqual(headers.get("Location"), expected_location)
                        if method == "GET":
                            self.assertIn(b"<html", body.lower())
                        else:
                            self.assertEqual(body, b"")

    def test_other_methods_are_405_without_redirect_or_fallback(self):
        for path in ROOTS:
            for method in ("POST", "PUT", "DELETE", "PATCH", "OPTIONS"):
                with self.subTest(method=method, path=path):
                    status, headers, _ = self.request(method, path)
                    self.assertEqual(status, 405)
                    self.assertEqual(headers.get("Allow"), "GET, HEAD")
                    self.assertNotIn("Location", headers)

    def test_slug_redirects_to_linkhop(self):
        for slug in ("my-custom-slug", "test123", "promo-2026"):
            with self.subTest(slug=slug):
                status, headers, _ = self.request("GET", f"/{slug}")
                self.assertEqual(status, 301)
                self.assertTrue(headers.get("Location", "").endswith(f"/l/{slug}"))

    def test_hub_pages_do_not_redirect_to_linkhop(self):
        for page in HUB_SINGLE_SEGMENT_PAGES:
            with self.subTest(page=page):
                status, headers, body = self.request("GET", f"/{page}")
                self.assertEqual(status, 200, f"/{page} should return 200 hub but got status {status}")
                self.assertEqual(body.decode("utf-8").strip(), "hub")
                self.assertNotIn("Location", headers)


if __name__ == "__main__":
    unittest.main()
