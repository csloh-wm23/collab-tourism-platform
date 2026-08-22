# JomCommunicate

JomCommunicate (Malaysia Language Real-time Communication System) is a student tourism communication system built with PHP 8, MySQL/MariaDB, HTML5, CSS3 and vanilla JavaScript. It is designed to run locally with XAMPP on Windows.

## XAMPP setup

1. Copy the project folder to `C:\xampp\htdocs\jomcommunicate`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Import [`database/jomcommunicate.sql`](database/jomcommunicate.sql). It creates and selects the `jomcommunicate` database.
5. Copy `.env.example` to a new local file named `.env`.
6. Open `http://localhost/jomcommunicate/`.

The `.env` file is ignored by Git and must never be committed.

## Environment configuration

Use these settings in `.env`, changing only values needed by your local setup:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jomcommunicate
DB_USER=root
DB_PASS=

GOOGLE_TRANSLATE_API_KEY=
```

The default XAMPP MySQL account normally has user `root` and a blank password. Use your actual local credentials if they differ.

## Create an administrator

Open PowerShell in the project folder and run:

```powershell
C:\xampp\php\php.exe scripts\create_admin.php "System Admin" admin@jom.local "ChangeMe123!"
```

Use a unique email and replace the example password. The script hashes the password and creates or promotes the account as an active administrator.

## Google Cloud setup

1. In Google Cloud, enable both the Cloud Translation API and Cloud Text-to-Speech API.
2. Create an API key and add it to `GOOGLE_TRANSLATE_API_KEY` in your local `.env`. If the key has API restrictions, allow both APIs.
3. Ensure the PHP cURL extension is enabled in XAMPP.
4. Restart Apache after changing PHP or environment configuration.

Speech requests go from the browser to `api/speech.php`. PHP creates the server-side Google REST request, so the Google key is never placed in HTML or JavaScript. The endpoint accepts at most 500 characters, supports only the listed languages, and applies a basic per-session request limit.

Translation requests similarly go through `api/translate.php`. The endpoint validates the language pair and text length, keeps the Google API key server-side, and rate-limits each session.

Configured Google voices:

- English: `en-US-Standard-C`
- Malay playback (using the Indonesian voice): `id-ID-Standard-A`
- Mandarin: `cmn-CN-Standard-A`
- Tamil: `ta-IN-Standard-A`

## Account roles and approval flow

- Guest: communication tools and browser-only saved phrases/history.
- Tourist: active immediately; private server-side history and persisted privacy choices.
- Business: starts pending and may use public communication tools, but cannot see or call Business Portal functions until approved.
- Editor: read-only service insights.
- Administrator: business approval/rejection and administration; no normal Business Portal.

Business approval flow:

1. A business registers; `users.status` and `businesses.verification_status` are `pending`.
2. The business may log in and sees a pending-approval notice.
3. An administrator reviews it under Administration.
4. Approve sets the business to `approved` and its user to `active`.
5. Reject sets the registration to `rejected` without treating it as a malicious suspension.
6. Approved owners can edit only their own profile and phrases. Suspended accounts fail protected requests immediately because each request reloads current status from MySQL.

Approval and rejection decisions are written to `audit_logs`.

## Supported languages

The current software supports:

- English (`en-US` speech recognition)
- Bahasa Melayu (`ms-MY` speech recognition)
- Mandarin (`zh-CN`)
- Tamil (`ta-MY`)

## Translation behavior

Curated tourism phrases are translated first by the built-in dictionary, so they remain fast and available without an external request. Other text is sent to Google Cloud Translation. English, Malay, Simplified Chinese, and Tamil are accepted as source and target languages. Google Cloud Text-to-Speech reads every result; Malay results intentionally use the Indonesian Google voice. Changing the source text, source language, target language or Swap selection makes the previous result stale until Translate is pressed again.

## Privacy and security notes

- Passwords use `password_hash()` and `password_verify()` and require at least eight characters.
- Successful login regenerates the session ID; session cookies are HttpOnly and SameSite=Lax.
- State-changing forms and APIs use CSRF tokens.
- Protected APIs reload role/status from MySQL instead of trusting stale session authorization.
- PDO prepared statements and ownership conditions scope history and business writes.
- Tourist translation-history and anonymous-analytics choices persist in `consent_records`.
- Anonymous analytics consent does not collect conversation content.
- Guest preferences and records remain in localStorage.
- `.env` and `.env.*` are ignored while `.env.example` remains tracked.

## Quick manual test

Use separate browser/private windows for different accounts:

1. As a guest, translate `Thank you` from English to Malay, play it with the Indonesian Google voice after setup, save the phrase, and refresh to verify localStorage.
2. Register a tourist, log in, translate, refresh, and verify private history persists. Disable Save Translation History, translate again, and verify no translation record is added.
3. Register a business and log in. Confirm the pending notice appears, the Business Portal navigation is absent, and `api/business.php` returns HTTP 403.
4. Log in as admin, approve the pending business, and verify it leaves the pending list.
5. Log back in as that business. Edit its profile, add a phrase, refresh, and verify both persist.
6. Reject another pending business or suspend a test user in MySQL while it is logged in; its next protected request must fail.
7. Log in as an editor and verify all four Insights totals load.
8. With two user accounts, confirm one cannot read or delete the other's record IDs and one business cannot select or edit another business profile.

Run syntax checks before submission:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
node --check assets\js\app.js
```

Node is used only as an optional JavaScript syntax checker; it is not an application dependency.
