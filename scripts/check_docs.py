#!/usr/bin/env python3
"""Offline checks for the Sail stubs and the Markdown documentation.

Three gates, in order: stub/doc synchronisation, unresolved placeholders and
local Markdown links. No network access and no dependencies outside the Python 3
standard library.
"""

from __future__ import annotations

import difflib
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOC = ROOT / "docs" / "user-manual" / "laravel-sail.md"
STUBS = ROOT / "stubs" / "sail"

STUB_NAMES = (
    "run_turso",
    "sail-entrypoint",
    "Dockerfile.fragment",
    "compose.fragment.yaml",
)

PLACEHOLDER = re.compile(r"<[A-Za-z_][A-Za-z0-9_-]*>")
MARKDOWN_LINK = re.compile(r"\[[^\]]*\]\(([^)]+)\)")


def fail(messages: list[str]) -> None:
    for message in messages:
        print(message, file=sys.stderr)
    raise SystemExit(1)


def check_stub_sync() -> None:
    doc_lines = DOC.read_text().splitlines()
    problems: list[str] = []

    for name in STUB_NAMES:
        begin = f"<!-- BEGIN STUB: {name} -->"
        end = f"<!-- END STUB: {name} -->"

        if doc_lines.count(begin) != 1 or doc_lines.count(end) != 1:
            problems.append(
                f"{DOC.relative_to(ROOT)}: expected exactly one BEGIN and one END "
                f"marker for {name}"
            )
            continue

        start = doc_lines.index(begin)
        stop = doc_lines.index(end)
        if stop <= start:
            problems.append(f"{DOC.relative_to(ROOT)}: inverted markers for {name}")
            continue

        # The region includes the ``` fence lines. The canonical stub files do
        # not contain those fences, so compare only the lines *inside* them.
        region = doc_lines[start + 1 : stop]
        if len(region) < 2 or region[0].startswith("```") is False or region[-1] != "```":
            problems.append(
                f"{DOC.relative_to(ROOT)}: stub {name} is not wrapped in a fenced block"
            )
            continue

        in_doc = region[1:-1]
        in_stub = (STUBS / name).read_text().splitlines()
        if in_doc != in_stub:
            diff = difflib.unified_diff(
                in_stub,
                in_doc,
                fromfile=str(STUBS.relative_to(ROOT) / name),
                tofile=f"{DOC.relative_to(ROOT)} ({name})",
                lineterm="",
            )
            problems.append("stub/doc drift for " + name + ":\n" + "\n".join(diff))

    if problems:
        fail(problems)
    print("OK: stub sync")


def check_placeholders() -> None:
    files = [DOC, *sorted(p for p in (ROOT / "stubs").rglob("*") if p.is_file())]
    problems: list[str] = []

    for path in files:
        for number, line in enumerate(path.read_text().splitlines(), 1):
            if PLACEHOLDER.search(line) or "/etc/php/8.3/" in line:
                problems.append(f"{path.relative_to(ROOT)}:{number}: unresolved placeholder or hardcoded PHP path: {line.strip()}")

    if problems:
        fail(problems)
    print("OK: placeholders")


def resolve_link(linker: Path, target: str) -> bool:
    if target.startswith(("http://", "https://", "mailto:", "#")) or not target:
        return True

    target = target.split("#", 1)[0].strip()
    if not target:
        return True

    if target.startswith("/"):
        relative = target.lstrip("/")
        if relative.startswith("turso-php-installer/"):
            relative = relative[len("turso-php-installer/") :]
        base = ROOT / "docs"
    else:
        relative = target
        base = linker.parent

    path = base / relative
    trimmed = Path(str(path).rstrip("/"))
    candidates = (path, Path(str(trimmed) + ".md"), trimmed / "index.md")
    return any(candidate.is_file() for candidate in candidates)


def check_links() -> None:
    files = [ROOT / "README.md", *sorted((ROOT / "docs").rglob("*.md"))]
    problems: list[str] = []

    for path in files:
        for number, line in enumerate(path.read_text().splitlines(), 1):
            for target in MARKDOWN_LINK.findall(line):
                if not resolve_link(path, target):
                    problems.append(f"{path.relative_to(ROOT)}:{number}: unresolved link: {target}")

    if problems:
        fail(problems)
    print("OK: links")


if __name__ == "__main__":
    check_stub_sync()
    check_placeholders()
    check_links()
