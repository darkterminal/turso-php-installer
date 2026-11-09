#!/usr/bin/env python3

"""
Requirement check for the sqld certificate utilities.

Verifies that the Python libraries required by the certificate
generation scripts (e.g. `cryptography`) are importable with the
interpreter that will run them.

The check never installs anything silently: it only inspects the
environment and exits non-zero with remediation hints when a
library is missing.
"""

import importlib.util
import sys

REQUIRED_LIBRARIES = ["cryptography"]


def is_library_installed(library_name):
    """Check if a library is importable."""
    return importlib.util.find_spec(library_name) is not None


missing = [name for name in REQUIRED_LIBRARIES if not is_library_installed(name)]

if missing:
    print("Missing Python libraries: " + ", ".join(missing))
    print("The sqld server certificate utilities require these libraries.")
    print("Install them inside the Python environment used by your system, e.g.:")
    print(
        "    python3 -m pip install --user "
        + " ".join(missing)
    )
    print("or inside a dedicated virtual environment:")
    print(
        "    python3 -m venv venv && source venv/bin/activate && python3 -m pip install "
        + " ".join(missing)
    )
    print("(PEP 668 managed environments require a venv or --break-system-packages)")
    sys.exit(1)

print("Required Python libraries are available: " + ", ".join(REQUIRED_LIBRARIES))
sys.exit(0)
