CREATE TABLE IF NOT EXISTS cookies
(
  host_key          TEXT NOT NULL,
  domain            TEXT NOT NULL,
  path              TEXT NOT NULL,
  name              TEXT NOT NULL,
  value             TEXT NOT NULL,
  created_at        INTEGER NOT NULL,
  expires_at        INTEGER NOT NULL,
  accessed_at       INTEGER NOT NULL,
  updated_at        INTEGER NOT NULL,
  hostonly          INTEGER NOT NULL,
  secureonly        INTEGER NOT NULL,
  httponly          INTEGER NOT NULL,
  persistent        INTEGER NOT NULL,
  samesite          TEXT NOT NULL,
  source_scheme     TEXT NOT NULL,
  source_port       INTEGER NOT NULL,
  same_party        INTEGER NOT NULL,
  priority          INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS cookies_idx ON cookies(host_key, name, path);

CREATE TABLE IF NOT EXISTS meta
(
  key   TEXT NOT NULL UNIQUE PRIMARY KEY,
  value TEXT
);

INSERT OR IGNORE INTO meta VALUES ('version', '1');
