#!/usr/bin/env python3

import argparse
from pathlib import Path
from modules.cert_generator import CertificateGenerator

def parse_args():
    """Parse command-line arguments."""
    parser = argparse.ArgumentParser(description="Generate X.509 certificates for testing.")
    parser.add_argument(
        "--store-location",
        type=str,
        default="./cert_results",
        help="Location to store the certificates and keys (default: ./cert_results)",
    )
    return parser.parse_args()

if __name__ == "__main__":
    args = parse_args()

    store_location = Path(args.store_location)

    ca_util = CertificateGenerator(store_location=store_location)
    result = ca_util.list_certificates_in_directory(store_location)
    if result:
        print("File created.")
    else:
        print("Failed to create file.")
