#!/usr/bin/env python3
"""utility that generates X.509 certificates for testing

the following certificates and their keys are stored in your working directory:
- ca_cert.pem, ca_key.pem
- server_cert.pem, server_key.pem
- client_cert.pem, client_key.pem
"""
import argparse
from modules.cert_generator import CertificateGenerator

def parse_args():
    """Parse command-line arguments."""
    parser = argparse.ArgumentParser(description="Generate X.509 certificates for testing.")
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

    ca_util = CertificateGenerator(validity_days=args.days, store_location=args.store_location)

    ca_key = ca_util.gen_key()
    ca_cert = ca_util.gen_ca_cert(ca_key)
    ca_util.store_cert_chain_and_key([ca_cert], ca_key, "ca")

    server_key = ca_util.gen_key()
    server_cert = ca_util.gen_peer_cert(ca_cert, ca_key, server_key, "sqld", ["sqld"])
    ca_util.store_cert_chain_and_key([server_cert, ca_cert], server_key, "server")

    client_key = ca_util.gen_key()
    client_cert = ca_util.gen_peer_cert(ca_cert, ca_key, client_key, "sqld replica", [])
    ca_util.store_cert_chain_and_key([client_cert, ca_cert], client_key, "client")

    print(f"these are development certs, they will expire at {args.days} days")
