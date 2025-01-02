#!/usr/bin/env python3

class CertificateDetails:
    def __init__(self, subject, issuer, not_valid_before, not_valid_after):
        self.subject = subject
        self.issuer = issuer
        self.not_valid_before = not_valid_before
        self.not_valid_after = not_valid_after

    def __str__(self):
        return (
            f"Certificate Details:\n"
            f"  Subject: {self.subject}\n"
            f"  Issuer: {self.issuer}\n"
            f"  Valid From: {self.not_valid_before}\n"
            f"  Valid To: {self.not_valid_after}"
        )
