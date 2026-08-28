# JomCommunicate

JomCommunicate is an XAMPP-compatible Malaysia tourism communication system built with HTML, CSS, vanilla JavaScript, PHP and MySQL. It follows the project proposal's five modules and supports exactly:

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

If you already imported an older version of the database, import `database/migrations/20260828_document_features.sql` once instead of deleting your data.

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

Text and voice translation, automatic language detection, two-way mode, scenario selection, confidence display, low-confidence confirmation, alternative/context suggestions, Malaysian terminology, unclear-translation reporting and a large-screen message display.

### Smart Tourism Assistance

Restaurant, hotel, transportation, shopping, medical and emergency workflows; suggested questions, quick replies, dietary/allergy/religious/spice communication, bilingual emergency card, cultural tips and destination/offline-ready phrase packs.

### Tourism Business Communication

Registration and administrator approval, public business profile and QR link, account-free tourist page, service/payment/menu/facility information, approved phrase templates, two-way quick replies, FAQs, business-managed terms and interaction reports.

### Personalised Tourist and Journey

Guest and registered access, preferred language, accessibility, dietary, allergy and optional emergency preferences, favourites, history, destination packs, recommendations, consent controls and complete saved-journey deletion.

### Communication Intelligence

Anonymous consented analysis for languages, scenarios, unclear and low-confidence input, terms, locations, business types, peak periods and repeated enquiries. Editor/admin reports include filters, CSV export and improvement recommendations.

## Accounts and security

- Tourist accounts become active immediately.
- Business accounts require administrator approval.
- Editors and administrators can view anonymous intelligence; administrators manage business approvals.
- Passwords use PHP password hashing, writes use CSRF protection, database operations use prepared statements, and protected requests reload current account status.
- Five failed password attempts lock the account for 15 minutes. A successful login resets the counter.
- CAPTCHA and registration-spam protection are intentionally outside this demo's requested scope.

Create the first administrator from a terminal:

```bash
php scripts/create_admin.php "Admin Name" "admin@example.com"
```

## Automated tests

GitHub Actions runs PHP lint, JavaScript syntax checks, a clean MySQL import, application requirement checks and database/schema checks on every push and pull request.

Run locally:

```bash
php tests/run.php
node --check assets/js/app.js
```

With the database imported and MySQL running:

```bash
php tests/database.php
```

## Privacy

Conversation history is saved only when enabled. Anonymous analytics requires explicit consent and stores no user ID or conversation content. Users can delete their saved journey records, packs and consent history.
