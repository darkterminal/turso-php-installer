#!/usr/bin/env python3

import importlib.util
import subprocess
import sys

def is_library_installed(library_name):
    """Check if a library is installed."""
    return importlib.util.find_spec(library_name) is not None

def install_library(library_name):
    """Install the library using pip."""
    try:
        print(f"Installing library '{library_name}'...")
        subprocess.check_call([sys.executable, "-m", "pip", "install", library_name])
        print(f"Library '{library_name}' installed successfully.")
    except subprocess.CalledProcessError as e:
        print(f"Failed to install library '{library_name}'. Error: {e}")

library = "cryptography"

if is_library_installed(library):
    print(f"Library '{library}' is already installed.")
else:
    print(f"Library '{library}' is not installed.")
    install_library(library)
