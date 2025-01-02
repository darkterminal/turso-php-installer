import datetime
import os
import json
import tempfile
from modules.certificate_details import CertificateDetails
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

class CertificateGenerator:
    def __init__(self, validity_days=3, store_location="./cert_results"):
        self.validity_days = validity_days
        self.store_location = store_location
        self.not_before = datetime.datetime.now(datetime.timezone.utc)
        self.not_after = self.not_before + datetime.timedelta(days=validity_days)

        # Ensure the store location exists
        if not os.path.exists(self.store_location):
            os.makedirs(self.store_location)

    def gen_key(self):
        return Ed25519PrivateKey.generate()

    def gen_ca_cert(self, ca_key):
        ca_name = x509.Name([
            x509.NameAttribute(x509.oid.NameOID.COMMON_NAME, "sqld dev CA"),
        ])
        return x509.CertificateBuilder() \
            .issuer_name(ca_name) \
            .subject_name(ca_name) \
            .public_key(ca_key.public_key()) \
            .serial_number(x509.random_serial_number()) \
            .not_valid_before(self.not_before) \
            .not_valid_after(self.not_after) \
            .add_extension(x509.BasicConstraints(ca=True, path_length=None), critical=True) \
            .add_extension(x509.KeyUsage(
                key_cert_sign=True,
                crl_sign=True,
                digital_signature=False,
                content_commitment=False,
                key_encipherment=False,
                data_encipherment=False,
                key_agreement=False,
                encipher_only=False,
                decipher_only=False,
            ), critical=True) \
            .sign(ca_key, None)

    def gen_peer_cert(self, ca_cert, ca_key, peer_key, peer_common_name, peer_dns_names):
        return x509.CertificateBuilder() \
            .issuer_name(ca_cert.subject) \
            .subject_name(x509.Name([
                x509.NameAttribute(x509.oid.NameOID.COMMON_NAME, peer_common_name),
            ])) \
            .public_key(peer_key.public_key()) \
            .serial_number(x509.random_serial_number()) \
            .not_valid_before(self.not_before) \
            .not_valid_after(self.not_after) \
            .add_extension(x509.BasicConstraints(ca=False, path_length=None), critical=True) \
            .add_extension(x509.KeyUsage(
                digital_signature=True,
                key_encipherment=False,
                key_cert_sign=False,
                crl_sign=False,
                content_commitment=False,
                data_encipherment=False,
                key_agreement=False,
                encipher_only=False,
                decipher_only=False,
            ), critical=True) \
            .add_extension(x509.SubjectAlternativeName([
                x509.DNSName(dns_name) for dns_name in peer_dns_names
            ]), critical=False) \
            .sign(ca_key, None)

    def store_cert_chain_and_key(self, cert_chain, key, name):
        cert_file = f"{self.store_location}/{name}_cert.pem"
        key_file = f"{self.store_location}/{name}_key.pem"

        with open(cert_file, "wb") as f:
            for cert in cert_chain:
                f.write(cert.public_bytes(encoding=serialization.Encoding.PEM))
        print(f"Stored cert {name!r} into {cert_file!r}")

        with open(key_file, "wb") as f:
            f.write(key.private_bytes(
                encoding=serialization.Encoding.PEM,
                format=serialization.PrivateFormat.PKCS8,
                encryption_algorithm=serialization.NoEncryption(),
            ))
        print(f"Stored private key {name!r} into {key_file!r}")
    
    def load_certificate_from_file(self, file_path):
        with open(file_path, "rb") as f:
            pem_data = f.read()
        # Decode the PEM and return the certificate object
        decoded = x509.load_pem_x509_certificate(pem_data)

        details = CertificateDetails(
            subject=decoded.subject,
            issuer=decoded.issuer,
            not_valid_before=decoded.not_valid_before_utc.astimezone(),
            not_valid_after=decoded.not_valid_after_utc.astimezone(),
        )

        return decoded, details

    def load_key_from_file(self, file_path):
        with open(file_path, "rb") as f:
            key_data = f.read()

        return serialization.load_pem_private_key(
            key_data,
            password=None,  # Use the correct password if the key is encrypted
        )
    
    def list_certificates_in_directory(self, directory_path):
        pem_files = [f for f in os.listdir(directory_path) if f.endswith('_cert.pem')]
        certificates = []

        for pem_file in pem_files:
            file_path = os.path.join(directory_path, pem_file)

            try:
                with open(file_path, "rb") as f:
                    pem_data = f.read()

                # Decode the PEM file
                decoded = x509.load_pem_x509_certificate(pem_data)

                # Extract certificate details
                cert_details = {
                    "file_name": pem_file,
                    "subject": decoded.subject.rfc4514_string(),
                    "issuer": decoded.issuer.rfc4514_string(),
                    "not_valid_before": decoded.not_valid_before_utc.isoformat(),
                    "not_valid_after": decoded.not_valid_after_utc.isoformat(),
                }
                certificates.append(cert_details)
            except Exception as e:
                print(f"Error processing {pem_file}: {e}")

        # Convert certificate details to JSON
        certificates_json = json.dumps(certificates, indent=4)

        # Store the JSON in the system's temporary directory with a fixed name
        temp_dir = tempfile.gettempdir()  # Get the system's temporary directory
        temp_file_path = os.path.join(temp_dir, "list_cacert_results.json")
        
        try:
            with open(temp_file_path, "w") as temp_file:
                temp_file.write(certificates_json)
            print(f"Certificate details stored in file: {temp_file_path}")
        except Exception as e:
            print(f"Error writing file: {e}")
            return False

        # Check if the file exists and return True or False
        return os.path.exists(temp_file_path)
