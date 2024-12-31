SELECT
    id,
    db_name,
    full_access_token,
    read_only_token,
    public_key_pem,
    public_key_base64,
    expiration_day,
    created_at,
    updated_at
FROM
    tokens
ORDER BY
    created_at DESC
