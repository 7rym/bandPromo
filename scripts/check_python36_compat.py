#!/usr/bin/env python3
"""Fail if scripts/ contain constructs that break CPython 3.6.9.

Host Python 3.6.9 is the hard floor for every file under scripts/.
This checker is intentionally pattern-based so it runs on newer CI Pythons.
"""

from __future__ import print_function

import os
import re
import sys


SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT_SCRIPTS = SCRIPT_DIR

# Patterns that are SyntaxError or TypeError on CPython 3.6.
FORBIDDEN = (
    (
        re.compile(r"from\s+__future__\s+import\s+annotations"),
        "from __future__ import annotations (requires Python 3.7+)",
    ),
    (
        re.compile(r"\btext\s*=\s*True\b"),
        "subprocess text=True (use universal_newlines=True)",
    ),
    (
        re.compile(r"\bcapture_output\s*=\s*True\b"),
        "subprocess capture_output=True (use stdout=/stderr= PIPE)",
    ),
    (
        re.compile(r"\bonexc\s*="),
        "shutil.rmtree onexc= (use onerror= on Python 3.6)",
    ),
    (
        # PEP 604 in return annotations.
        re.compile(r"->[^#\n]*\|"),
        "PEP 604 union in return annotation (use typing.Optional / Union)",
    ),
    (
        # PEP 604 Optional-style forms that never appear as bitwise ops.
        re.compile(r"\b[A-Za-z_][\w\.]*\s*\|\s*None\b"),
        "PEP 604 X | None (use typing.Optional)",
    ),
    (
        re.compile(r"\bNone\s*\|\s*[A-Za-z_][\w\.]*\b"),
        "PEP 604 None | X (use typing.Optional)",
    ),
    (
        re.compile(r"\b(?:list|dict|tuple|set)\[[^\]]+\]"),
        "PEP 585 builtin generics (use typing.List / Dict / Tuple / Set)",
    ),
)


def iter_python_files(root):
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            if name.endswith(".py"):
                yield os.path.join(dirpath, name)


def check_file(path):
    # type: (str) -> list
    problems = []
    try:
        with open(path, "r", encoding="utf-8") as handle:
            lines = handle.readlines()
    except UnicodeDecodeError:
        with open(path, "r") as handle:
            lines = handle.readlines()

    for lineno, line in enumerate(lines, start=1):
        stripped = line.lstrip()
        if stripped.startswith("#"):
            continue
        for pattern, message in FORBIDDEN:
            if pattern.search(line):
                # Allow this checker to keep a future import only if we never add one —
                # and allow comments already skipped.
                problems.append((lineno, message, line.rstrip("\n")))
    return problems


def main():
    # type: () -> int
    failures = 0
    self_name = os.path.basename(__file__)
    for path in sorted(iter_python_files(ROOT_SCRIPTS)):
        if os.path.basename(path) == self_name:
            # This file documents forbidden patterns in string messages.
            continue
        rel = os.path.relpath(path, os.path.dirname(ROOT_SCRIPTS)).replace("\\", "/")
        for lineno, message, line in check_file(path):
            print("{0}:{1}: {2}".format(rel, lineno, message))
            print("  {0}".format(line))
            failures += 1

    if failures:
        print(
            "Python 3.6 compat check failed: {0} issue(s) in scripts/.".format(failures),
            file=sys.stderr,
        )
        return 1

    print("Python 3.6 compat check passed for scripts/.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
