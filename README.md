# JomCommunicate — fixed implementation

JomCommunicate is a PHP 8 + MySQL tourism communication system based on the Malaysia Language Real-time Communication System proposal.

## What this version fixes/adds

- tourist and tourism-business registration
- login/logout with PHP sessions and password hashing
- role-based navigation for tourist, business, editor and admin users
- business registration approval flow
- user-scoped translation history (one user cannot read/delete another user's history)
- CSRF protection on state-changing API requests
- translation history now actually saves `translation` records
- prevents saving a phrase after changing the source text without translating again
- correct speech-recognition locale mapping for English, Malay, Mandarin and Malaysian Tamil
- secure Azure Speech TTS endpoint in PHP
- Malay Azure voice: `ms-MY-YasminNeural`
- Malaysian Tamil Azure voice: `ta-MY-KaniNeural`
- business profile writes to the `businesses` table
- business phrases write to `business_phrases`
- guest access still works using browser localStorage

## 1. Install with XAMPP

1. Copy the project folder to:
   `C:\xampp\htdocs\jomcommunicate`
2. Start Apache and MySQL.
3. Import `database/jomcommunicate.sql` in phpMyAdmin.
4. Copy `.env.example` to `.env`.
5. Open `http://localhost/jomcommunicate/`.

## 2. Create the first administrator

Open a terminal in the project folder and run:

```powershell
C:\xampp\php\php.exe scripts\create_admin.php "System Admin" admin@jom.local "ChangeMe123!"
```

Then log in with that email and password.

## 3. Configure Azure Speech

Create an Azure Speech resource, then open `.env` and set:

```env
AZURE_SPEECH_KEY=YOUR_REAL_KEY
AZURE_SPEECH_REGION=southeastasia
```

Use the exact region shown for your Azure Speech resource. Do not commit `.env`.

The browser sends translated text to `api/speech.php`. PHP keeps the Azure key on the server, sends SSML to Azure, receives MP3 audio, and returns it to the browser.

## Important translation note

This package fixes voice output, authentication and the concrete bugs found in the original repository. The actual text translator is still the small demo dictionary from the prototype. For production-quality arbitrary translation, add the proposed Google Cloud Translation API (or another translation API) as a separate server-side endpoint.

## Roles

- Guest: communication + local browser history
- Tourist: communication + private server-side history
- Business: communication + business portal; initial registration is `pending`
- Editor: insights
- Admin: admin approvals + business/insight access

## Security notes

- passwords use `password_hash()` / `password_verify()`
- sessions use HttpOnly and SameSite=Lax cookies
- database operations use PDO prepared statements
- API history is scoped to the logged-in user
- write requests use CSRF tokens
- Azure key stays in `.env`, which is ignored by Git
