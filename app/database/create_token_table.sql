CREATE TABLE IF NOT EXISTS tokens (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    db_name TEXT NOT NULL UNIQUE,
    full_access_token TEXT NOT NULL,
    read_only_token TEXT NOT NULL,
    public_key_pem TEXT NOT NULL,
    public_key_base64 TEXT NOT NULL,
    expiration_day INTEGER NOT NULL,
    created_at TEXT DEFAULT (DATETIME('now', 'localtime')),
    updated_at TEXT DEFAULT (DATETIME('now', 'localtime'))
)
