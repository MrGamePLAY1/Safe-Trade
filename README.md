# Safe Trade — hardened dev build

A trust-first used-car marketplace for Ireland. Live listings carry uploaded vehicle paperwork,
buyers can hire a verified make-specialist mechanic for a viewing, private sellers can open a
car to approved dealer accounts, and buyer-safety tools are built into the flow.

## Stack

- PHP 8.0+ with PDO
- SQLite
- Vanilla JavaScript
- Hand-rolled CSS design system
- No build step or runtime package dependencies

## Run it

```bash
# Create and seed the local database + demo document files
php database/init.php

# Rebuild everything from scratch
php database/init.php --fresh

# Serve only the public web root
php -S localhost:8000 -t public
```

Requirements: PHP 8+ with `pdo_sqlite`, `fileinfo` and `mbstring`.

## Demo accounts

All demo passwords are `password123`.

| Email | Role | Notes |
|---|---|---|
| aoife@example.com | private | Owns the fully documented Corolla |
| conor@example.com | private | Owns the BMW + Focus |
| niamh@example.com | private | Owns the Golf + Octavia |
| dara@example.com | mechanic | Verified BMW / Audi / VW specialist |
| sinead@example.com | mechanic | Verified Toyota / Lexus specialist |
| dealer@example.com | dealer | Approved demo dealer |
| dealer2@example.com | dealer | Second approved demo dealer |

Public registration creates **private accounts only**. Mechanic and dealer roles are deliberately
not self-selectable; a real onboarding/verification process should grant those roles.

## Trust model

Safe Trade distinguishes three document states:

1. **Declared** — the seller says the document exists, but no file has been uploaded.
2. **Uploaded** — a PDF/JPG/PNG has been content-sniffed and stored outside the public web root.
3. **Verified** — reserved for evidence checked by a trusted verification workflow.

Only uploaded core documents contribute to the 0–100 completeness meter. A draft listing requires
at least one uploaded core vehicle document before it can be published.

Uploaded files live in `storage/uploads/` and are served through `public/document.php`, which
requires authentication and checks listing visibility before returning the file.

## Project tour

```
public/
  index.php        home + search
  listings.php     browse/filter
  listing.php      listing detail + document states
  sell.php         create a private draft
  documents.php    upload/declare documents + publish
  document.php     authorized document delivery
  mechanics.php    verified mechanic marketplace
  mechanic.php     profile, reviews, inspection booking
  auctions.php     dealer auctions + seller creation flow
  auction.php      atomic dealer bidding + countdown
  safety.php       checklist, check-in tool, VIN-check stub
  dashboard.php    role-aware listing/inspection/bid/check-in tools
  login.php
  register.php
  logout.php
  assets/

src/
  db.php           PDO + SQLite concurrency settings
  auth.php         secure sessions, role gates, CSRF
  helpers.php      validation, dates, document scoring, state rules
  layout.php       shared layout + security headers

database/
  schema.sql       schema and integrity constraints
  init.php         local database + demo data generator

storage/
  uploads/         private runtime document storage

tests/
  run.php          core business-rule smoke tests

.github/workflows/
  php.yml          PHP lint + tests on master and pull requests
```

## Security/business rules already enforced

- Password hashing with `password_hash()` / `password_verify()`
- Session ID regeneration after login
- HTTP-only, SameSite session cookie defaults
- CSRF protection on state-changing forms, including logout
- Prepared SQL statements
- Seller ownership checks for listing/document actions
- Private draft → publish listing lifecycle
- At least one uploaded core document required before publishing
- Document MIME sniffing, random filenames and 5 MB upload cap
- Documents stored outside the public web root
- Non-live listings hidden from non-owners
- Only verified mechanics are publicly discoverable
- Public users cannot self-register as mechanics/dealers
- Auction creation limited to private sellers
- Dealers cannot bid on their own vehicles
- Auction bids are serialized with an SQLite `BEGIN IMMEDIATE` transaction
- Minimum bid increment is re-checked inside the transaction
- Inspection state transitions are enforced server-side
- Safety check-ins validate future times and track overdue state
- UTC storage with Europe/Dublin display conversion
- Basic browser security headers/CSP
- Runtime SQLite/upload files ignored by Git

## Tests

```bash
php tests/run.php
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

GitHub Actions runs both checks automatically.

## Still required before a real launch

This remains a development build. Important production work still includes:

- email verification and account recovery
- an actual staff/admin workflow for dealer and mechanic approval
- login/API rate limiting and abuse controls
- malware scanning for uploaded documents
- object storage rather than local filesystem uploads
- privacy/retention controls for VRCs, finance letters and safety-contact data
- audit logs for verification and privileged actions
- background jobs for auction close/winner notifications
- SMS/WhatsApp delivery for overdue safety check-ins
- real VIN/history-check provider integration
- payments/escrow and financial compliance work
- listing photos and image moderation
- in-app messaging
- mechanic review submission gated to completed inspections
- structured migrations instead of rebuilding the SQLite file

## Product roadmap

1. Mechanic document-verification workflow
2. Check-in notifications
3. Vehicle-history API
4. In-app buyer/seller messaging
5. Dealer-auction payments
6. Escrow / verified transaction flow
7. Listing photos
8. Verified mechanic review submission


## Motor Checks
1. https://www.vehicleservices.gov.ie/cmv/search-result
2. https://www.motorcheck.ie/faqs/free-car-check/
3. https://www.cartell.ie/