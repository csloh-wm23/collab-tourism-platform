# Security review — 12 September 2026

Target: local TourLingo application. This is a targeted code/HTTP review, not a
penetration-test certification or a guarantee that no vulnerabilities remain.

## Fixed

- Consumer Gmail aliases now share a database-generated unique identity. This
  covers inserts and updates, including concurrent requests. Original contact
  addresses remain intact. Login uses the same identity. Other providers retain
  their dots and plus tags. Existing local data had no conflicting inboxes.
- Registration rejects addresses exceeding the database's 190-character limit.
- Invalid CSRF token types fail closed instead of causing a PHP type error.
- Admin and insights CSV exports prefix formula-like strings before serialization.
  Treat exported files as untrusted data; spreadsheet re-saving can alter escaping.
- Apache blocks direct access to secrets, Git metadata, database scripts, logs,
  maintenance folders and request.json. Directory listing is disabled, executable
  upload paths are blocked, and basic anti-sniffing/framing headers are set.

## Deployment

For an existing database, run database/migrations/20260912_email_identity.sql once
before deploying the new login/profile code. The clean schema includes the column.
If migration reports duplicates, investigate ownership; do not merge or delete
accounts automatically. The supplied local database has been migrated.

Apache must allow .htaccess and mod_rewrite. Other servers, including PHP's basic
development server, do not enforce these Apache rules: configure equivalent denies
or keep them strictly local. Do not expose the repository parent directory, which
may contain assessment artifacts or other unrelated files.

## Reviewed controls

- Login regenerates session ID and checks stored password hashes; failed attempts
  lock the account. Session cookies are HttpOnly/SameSite; Secure depends on HTTPS.
- Admin/review/insights check current database role and active status.
- Business and saved-record queries scope owner IDs to the current session.
- Profile uploads restrict actual MIME, dimensions, size and randomized extension;
  deletion is bounded to the profile directory.
- State-changing authenticated endpoints check CSRF. SQL values use prepared
  statements; reviewed dynamic table/column choices use allowlists.
- Browser HTTP checks confirmed guest denial on protected endpoints and continued
  access to the home page and static JavaScript after Apache hardening.

## Remaining production limitations

- No email ownership verification: alias deduplication does not prove who owns an
  inbox. Add verification before relying on email for identity or recovery.
- Public translation and visitor-question endpoints need shared, server-side/IP
  abuse limits and provider quotas before Internet exposure. Session-only limits
  can be bypassed by discarding cookies. A WAF/reverse-proxy policy is appropriate.
- Demo credentials are public fixtures; remove/disable those accounts on production,
  use a least-privilege database account and HTTPS, and disable detailed PHP errors.
- Password changes currently do not revoke every other existing session. Consider
  a per-account session version for deployment with real users.

No existing accounts, reports, uploaded pictures, or untracked request.json were
removed by this review. Assessment writing is a separate paused task.
