#!/usr/bin/env python3
"""Build or check public/hop-assets/apps.json, the HOP family asset manifest.

    python3 scripts/hop_assets.py build   # write public/hop-assets/apps.json
    python3 scripts/hop_assets.py check   # exit 1 if it is missing, stale or points at a missing file

The source is resources/hop-assets/apps.source.json. Every referenced file must exist
under public/hop-assets/<version>/; each gets an absolute URL and a sha384 SRI hash.
A version folder is immutable once deployed: change files by adding v2, never by
editing v1 in place (browsers and Cloudflare cache v1 for a year).
"""
import base64
import hashlib
import json
import re
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / "resources" / "hop-assets" / "apps.source.json"
OUT = ROOT / "public" / "hop-assets" / "apps.json"


def sri(path: pathlib.Path) -> str:
    return "sha384-" + base64.b64encode(hashlib.sha384(path.read_bytes()).digest()).decode()


def entry(folder: pathlib.Path, base: str, rel: str) -> dict:
    path = folder / rel
    if not path.is_file():
        raise SystemExit(f"missing asset: {path.relative_to(ROOT)}")
    return {"url": base + rel, "integrity": sri(path)}


def build() -> str:
    src = json.loads(SRC.read_text())
    version, base = src["version"], src["base"]
    folder = ROOT / "public" / "hop-assets" / version
    out = {"version": version, "base": base, "assets": {}, "apps": []}
    for key, rel in src["assets"].items():
        out["assets"][key] = entry(folder, base, rel)
    for app in src["apps"]:
        app = dict(app)
        app["icons"] = {k: entry(folder, base, v) for k, v in app["icons"].items()}
        out["apps"].append(app)
    return json.dumps(out, indent=2, ensure_ascii=False) + "\n"


def css_errors() -> list[str]:
    """An unclosed block silently swallows every rule after it, so check the nesting."""
    errors = []
    for css in sorted((ROOT / "public" / "hop-assets").rglob("*.css")):
        depth = 0
        for ch in re.sub(r"/\*.*?\*/", "", css.read_text(), flags=re.S):
            depth += {"{": 1, "}": -1}.get(ch, 0)
            if depth < 0:
                break
        if depth != 0:
            errors.append(f"{css.relative_to(ROOT)}: unbalanced braces")
    return errors


def main() -> None:
    mode = sys.argv[1] if len(sys.argv) > 1 else "check"
    text = build()
    if mode == "build":
        OUT.write_text(text)
        print(f"wrote {OUT.relative_to(ROOT)}")
    elif mode == "check":
        if errors := css_errors():
            raise SystemExit("\n".join(errors))
        if not OUT.is_file() or OUT.read_text() != text:
            raise SystemExit("public/hop-assets/apps.json is stale: run python3 scripts/hop_assets.py build")
        print("hop-assets manifest OK")
    else:
        raise SystemExit("usage: hop_assets.py build|check")


if __name__ == "__main__":
    main()
