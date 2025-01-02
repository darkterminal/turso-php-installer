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
    ca_cert = store_location / "ca_cert.pem"

    ca_util = CertificateGenerator(store_location=args.store_location)
    ca_cert, details = ca_util.load_certificate_from_file(ca_cert)

    print(f"Certificate Subject: {details.subject}")
    print(f"Certificate Issuer: {details.issuer}")
    print(f"Valid From: {details.not_valid_before}")
    print(f"Valid To: {details.not_valid_after}")
