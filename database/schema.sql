-- OpenBonnet — schema (SQLite)
-- Run via database/init.php (which also seeds demo data).

PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'private'
                CHECK (role IN ('private','mechanic','dealer')),
  phone         TEXT,
  county        TEXT,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE listings (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  make         TEXT NOT NULL,
  model        TEXT NOT NULL,
  year         INTEGER NOT NULL,
  reg          TEXT,              -- e.g. 191-D-12345
  vin          TEXT,
  mileage_km   INTEGER,
  price_eur    INTEGER NOT NULL,
  fuel         TEXT,
  transmission TEXT,
  colour       TEXT,
  county       TEXT,
  description  TEXT,
  status       TEXT NOT NULL DEFAULT 'live'
               CHECK (status IN ('draft','live','sale_agreed','sold','archived')),
  created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- The dossier: every document attached to a listing.
-- Core categories (used for the completeness score) are defined in src/helpers.php.
CREATE TABLE documents (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  listing_id  INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
  doc_type    TEXT NOT NULL,   -- nct_cert | service_history | vrc | timing_belt | finance_check | crash_report | other
  title       TEXT NOT NULL,
  file_path   TEXT,            -- stored under public/uploads (nullable: a doc can be "declared" before upload)
  note        TEXT,
  verified    INTEGER NOT NULL DEFAULT 0,  -- TODO: set by mechanic after inspection (workflow not built yet)
  uploaded_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE mechanic_profiles (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id          INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  headline         TEXT,
  bio              TEXT,
  base_county      TEXT,
  callout_fee_eur  INTEGER NOT NULL DEFAULT 60,
  years_experience INTEGER,
  verified         INTEGER NOT NULL DEFAULT 0
);

-- What a mechanic is actually good at, by make. Reviews reference makes too,
-- so ratings can be read per-speciality rather than as one blended number.
CREATE TABLE mechanic_specialties (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  mechanic_id INTEGER NOT NULL REFERENCES mechanic_profiles(id) ON DELETE CASCADE,
  make        TEXT NOT NULL,
  focus       TEXT             -- e.g. 'N47 timing chain wear', 'hybrid battery health'
);

CREATE TABLE inspections (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  mechanic_id   INTEGER NOT NULL REFERENCES mechanic_profiles(id) ON DELETE CASCADE,
  buyer_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  listing_id    INTEGER REFERENCES listings(id) ON DELETE SET NULL,
  scheduled_for TEXT,
  message       TEXT,
  status        TEXT NOT NULL DEFAULT 'requested'
                CHECK (status IN ('requested','confirmed','completed','cancelled')),
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE mechanic_reviews (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  mechanic_id   INTEGER NOT NULL REFERENCES mechanic_profiles(id) ON DELETE CASCADE,
  reviewer_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  inspection_id INTEGER REFERENCES inspections(id) ON DELETE SET NULL,
  car_make      TEXT NOT NULL,  -- anchors the review to a speciality
  car_model     TEXT,
  rating        INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
  found_issues  TEXT,           -- what the mechanic actually caught
  comment       TEXT,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Dealer-only auctions. Premium feature in the product plan;
-- no payment flow in this dev build (see README roadmap).
CREATE TABLE auctions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  listing_id  INTEGER NOT NULL UNIQUE REFERENCES listings(id) ON DELETE CASCADE,
  reserve_eur INTEGER,
  ends_at     TEXT NOT NULL,
  status      TEXT NOT NULL DEFAULT 'open'
              CHECK (status IN ('open','closed','cancelled')),
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE bids (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  auction_id INTEGER NOT NULL REFERENCES auctions(id) ON DELETE CASCADE,
  dealer_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  amount_eur INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Buyer safety: "I'm going to view a car" check-ins.
-- TODO: notify the trusted contact by SMS/email when overdue (no messaging in dev build).
CREATE TABLE safety_checkins (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  listing_id    INTEGER REFERENCES listings(id) ON DELETE SET NULL,
  meeting_place TEXT,
  contact_name  TEXT NOT NULL,
  contact_phone TEXT NOT NULL,
  expected_back TEXT NOT NULL,
  status        TEXT NOT NULL DEFAULT 'active'
                CHECK (status IN ('active','checked_in','overdue','cancelled')),
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_listings_status  ON listings(status);
CREATE INDEX idx_documents_listing ON documents(listing_id);
CREATE INDEX idx_bids_auction      ON bids(auction_id);
CREATE INDEX idx_reviews_mechanic  ON mechanic_reviews(mechanic_id);
