# OpenBonnet — dev build

A trust-first used-car marketplace for Ireland. The pitch: **every listing carries a dossier**
(NCT cert, service history, finance clearance…), buyers can **hire a make-specialist mechanic**
for the viewing, sellers can open their car to a **dealer-only auction**, and the whole thing is
wrapped in **buyer-safety tools**.

"OpenBonnet" is a placeholder name — rename freely.

## Stack

- PHP 8+ (no framework) with PDO
- SQLite (single file in `database/`)
- Vanilla JS + one hand-rolled stylesheet — no build step, no dependencies

## Run it

```bash
# 1. Create + seed the database
php database/init.php          # add --fresh to wipe and rebuild

# 2. Serve the public/ directory
php -S localhost:8000 -t public

# 3. Open http://localhost:8000
```

Requirements: PHP 8.0+ with `pdo_sqlite` (on Debian/Ubuntu: `apt install php-cli php-sqlite3`).

## Demo accounts

All passwords are `password123`.

| Email                | Role     | Notes                                    |
|----------------------|----------|------------------------------------------|
| aoife@example.com    | private  | Owns the fully-documented Corolla        |
| conor@example.com    | private  | Owns the BMW + Focus                     |
| niamh@example.com    | private  | Owns the Golf + Octavia (live auction)   |
| dara@example.com     | mechanic | BMW / Audi / VW specialist, 3 reviews    |
| sinead@example.com   | mechanic | Toyota / Lexus hybrid specialist         |
| dealer@example.com   | dealer   | Currently the high bidder on the Octavia |
| dealer2@example.com  | dealer   | Second dealer for auction testing        |

A good demo path: browse as a guest → open the Corolla (full dossier) → "Hire a Toyota specialist"
→ sign in as `conor@` and request an inspection → sign in as `sinead@` and confirm it from the
dashboard → sign in as `dealer@` and bid on the Octavia auction.

## Project tour

```
public/            web root — one PHP file per screen
  index.php        home: hero + featured dossier card + search
  listings.php     browse/filter
  listing.php      detail + dossier panel + safety/inspection actions
  sell.php         create listing → documents.php
  documents.php    dossier builder (upload or declare docs, delete)
  mechanics.php    marketplace, filterable by make speciality
  mechanic.php     profile, make-anchored reviews, booking form
  auctions.php     open auctions + create-auction (premium) flow
  auction.php      bid history + dealer bid form + countdown
  safety.php       checklist, check-in tool, VIN-check stub
  dashboard.php    role-aware: listings / inspection queue / bids
  login.php, register.php, logout.php
  assets/          style.css (design system), app.js (countdowns etc.)
  uploads/         document files land here (gitignore in production)
src/
  db.php           PDO singleton + db_row/db_all/db_exec helpers
  auth.php         sessions, role gates, CSRF helpers
  helpers.php      formatting, dossier scoring, plate/meter partials
  layout.php       page_header()/page_footer()
database/
  schema.sql       full schema, commented
  init.php         creates DB + seeds the demo data above
```

### Ideas the code encodes

- **The dossier** — six core document categories (`CORE_DOCS` in `src/helpers.php`) drive a
  0–100 completeness score and the segmented meter shown on every card. Docs can be *declared*
  before a file is uploaded; buyers see the difference. `verified` exists on documents but is
  currently only set by seed data (see roadmap).
- **Speciality-anchored reviews** — mechanic reviews store `car_make`, so a profile can show
  "4.8★ across 5 BMW inspections" instead of one blended number, and the marketplace filters
  by declared speciality.
- **Dealer auctions** — one auction per listing, dealer-only bidding, optional reserve,
  lazy close on page load. Flagged throughout the UI as the premium/monetised feature.
- **Safety** — check-ins with a trusted contact + expected-back time; viewing checklist;
  VIN history check stubbed where a real API would plug in.

## Roadmap / TODOs

Rough order of value, based on the research that shaped this build:

1. **Escrow / verified payment** — the highest-leverage safety feature and the natural
   transaction-fee revenue line. Hold funds until ownership transfer is confirmed.
2. **Mechanic verification workflow** — after a completed inspection, let the mechanic mark
   dossier documents as sighted/verified (the `verified` column and stamp UI already exist).
3. **Check-in alerts** — SMS/WhatsApp to the trusted contact when a check-in goes overdue
   (Twilio or similar). The data model is done; only the notifier is missing.
4. **History-check API** — wire the VIN stub on `safety.php` (and auto-pull into new listings
   from `sell.php`) to a provider; cache results as dossier documents.
5. **In-app messaging** — keep buyer↔seller contact on-platform until a viewing is agreed;
   this is both a safety feature and the anti-disintermediation moat.
6. **Payments for the auction tier** — listing fee or success fee; Stripe is the obvious start.
7. **Photos** — real image uploads for listings (the `car_thumb()` placeholder is deliberate
   dev-build minimalism).
8. **Mechanic review submission** — reviews are seeded but there's no form yet; gate it on
   completed inspections so every review is provably tied to a real job.
9. Hardening for production: rate limiting, email verification, upload content-sniffing,
   CSP headers, and moving uploads out of the web root.

## Security notes (dev build)

CSRF tokens, password hashing, prepared statements and per-owner checks are in place.
Uploads are extension-allowlisted and renamed but **not** content-sniffed; there's no rate
limiting or email verification. Treat it as a local development build, not a deployable product.
