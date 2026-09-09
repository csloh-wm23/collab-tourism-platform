# TourLingo

TourLingo is an XAMPP-compatible Malaysia tourism communication system built with HTML, CSS, vanilla JavaScript, PHP and MySQL. It combines real-time translation, situation-ready travel support, personalised journey tools, tourism-business communication and anonymous service insights. It supports exactly:

- Bahasa Malaysia
- English
- Mandarin Chinese
- Indonesian
- Thai

## XAMPP setup

1. Put this project in `C:\xampp\htdocs\jomcommunicate`.
2. Start Apache and MySQL in XAMPP.
3. For a new database, import `database/jomcommunicate.sql` in phpMyAdmin.
4. Copy `.env.example` to `.env` and add the Google Cloud API key.
5. Open `http://localhost/jomcommunicate/`.

If you already imported an older version of the database, import `database/migrations/20260828_document_features.sql`, `database/migrations/20260905_travease_profile.sql`, and `database/migrations/20260905_profile_picture.sql` once instead of deleting your data.

## Google Cloud configuration

Enable Cloud Translation API and Cloud Text-to-Speech API for the project. The API key remains in server-side `.env` and is never committed or sent to browser JavaScript.

Text-to-Speech voices:

- English: `en-US-Standard-C`
- Bahasa Malaysia: `ms-MY-Standard-A`
- Mandarin Chinese: `cmn-CN-Standard-A`
- Indonesian: `id-ID-Standard-A`
- Thai: `th-TH-Standard-A`

## Implemented proposal modules

### Real-Time Tourism Communication

Text and voice translation, automatic text-language detection, two-way mode, scenario selection, curated alternative/context suggestions, Malaysian terminology, unclear-translation reporting and a large-screen message display. Browser speech recognition requires the speaker to choose the spoken language. Google Cloud Translation Basic does not provide a confidence score, so the interface says that it is unavailable instead of inventing a percentage; genuine browser voice-recognition confidence is still used for low-confidence confirmation and analysis.

### Smart Tourism Assistance

Restaurant, hotel, transportation, shopping, medical and emergency workflows; suggested questions, quick replies, dietary/allergy/religious/spice communication, dynamically translated bilingual emergency cards, cultural tips and destination/offline-ready phrase packs. A requested destination without its own pack is clearly identified as a Malaysia fallback and is never saved under a misleading destination name.

### Tourism Business Communication

Registration, rejection correction/resubmission and administrator approval; multilingual public business profiles; QR link with a visible link fallback; account-free tourist pages; service/payment/menu/facility information; publishable and editable phrase templates, quick replies, FAQs and terms; custom tourist questions; and interaction/repeated-question reports.

### Personalised Tourist and Journey

Guest and registered access, dedicated profile management with validated JPG/PNG/WebP profile-picture uploads, preferred language, accessibility, dietary, allergy and optional emergency preferences, password changes, persistent favourites, history, destination packs, situation-based phrase recommendations, consent controls and complete profile/saved-journey/offline-pack deletion.

### Communication Intelligence

Anonymous, server-verified consent analysis for languages, scenarios, unclear and low-confidence voice input, terms, locations, business types, peak periods and repeated free-form enquiries. Editor/admin reports include date/language/scenario/location/business-type filters, complete multi-section CSV export and data-specific improvement recommendations. Translation issue reports retain analytical dimensions and a one-way text fingerprint, not reporter identity or conversation text.

Administrators also have a live operational report for user status by role, business-review status, recent audit activity and pending approvals. The full administration report exports as either an Excel-friendly CSV or a polished multi-page PDF.

## Accounts and security

- Tourist accounts become active immediately.
- Business accounts require administrator approval.
- Editors and administrators can view anonymous intelligence; administrators manage business approvals.
- Passwords use PHP password hashing, writes use CSRF protection, database operations use prepared statements, and protected requests reload current account status.
- Five failed password attempts lock the account for 15 minutes. A successful login resets the counter.
- CAPTCHA and registration-spam protection are intentionally outside this demo's requested scope.

Create the first administrator from a terminal:

```bash
php scripts/create_admin.php "Admin Name" "admin@example.com" "StrongPassword"
```

### Demo accounts

Create or reset every presentation account, including all roles, account states, and a populated Administration review queue:

```bash
php scripts/seed_demo_accounts.php
```

All accounts use the password `TourLingoDemo#2026`:

- `tourist.demo@tourlingo.test` — active tourist
- `business.pending@tourlingo.test` — pending business
- `business.approved@tourlingo.test` — approved business with sample public content
- `business.rejected@tourlingo.test` — rejected business
- `editor.demo@tourlingo.test` — active insights editor
- `admin.demo@tourlingo.test` — active administrator
- `suspended.demo@tourlingo.test` — suspended tourist
- `test.cafe@gmail.com` — Rasa Malaysia Cafe
- `test.hotel@gmail.com` — City Lights Boutique Hotel
- `test.transport@gmail.com` — KL Easy Ride
- `test.touragency@gmail.com` — Discover Malaysia Tours
- `test.shop@gmail.com` — Local Treasures Market

The script is idempotent. Running it again restores the documented roles and states, resets login locks and returns the five Gmail businesses to the pending queue.

## Automated tests

GitHub Actions runs PHP lint, JavaScript syntax and behaviour tests, clean-schema and migration imports, application requirement checks, database/schema/workflow checks, and guest HTTP consent/analytics smoke tests on every push and pull request. External Google Cloud responses and full visual browser rendering still require manual testing with configured credentials and XAMPP.

Run locally:

```bash
php tests/run.php
node --check assets/js/app.js
node tests/frontend.js
```

With the database imported and MySQL running:

```bash
php tests/database.php
```

## Privacy

Conversation history is saved only when enabled. Anonymous analytics requires explicit consent and stores no user ID or conversation content. Users can delete their saved journey records, packs and consent history.
