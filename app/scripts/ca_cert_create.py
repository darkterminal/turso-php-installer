#!/usr/bin/env python3
"""
utility that generates X.509 certificates for testing

the following certificates and their keys are stored in your working directory:
- ca_cert.pem, ca_key.pem
"""
import argparse
from pathlib import Path
from modules.cert_generator import CertificateGenerator

def parse_args():
    """Parse command-line arguments."""
    parser = argparse.ArgumentParser(description="Generate X.509 certificates for testing.")
    parser.add_argument(
        "--custom-name",
        type=str,
        default="ca",
        help="Name for the CA certificate (default: ca)",
    )
    parser.add_argument(
        "--days",
        type=int,
        default=3,
        help="Number of days for certificate validity (default: 3)",
    )
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

    ca_util = CertificateGenerator(validity_days=args.days, store_location=store_location)

    ca_key = ca_util.gen_key()
    ca_cert = ca_util.gen_ca_cert(ca_key)
    ca_util.store_cert_chain_and_key([ca_cert], ca_key, args.custom_name)
