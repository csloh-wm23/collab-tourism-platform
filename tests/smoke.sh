#!/usr/bin/env bash
set -euo pipefail
trap 'echo "FAIL: guest smoke check at line $LINENO: $BASH_COMMAND" >&2' ERR
php -S 127.0.0.1:8080 -t . > /tmp/jomcommunicate-php.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT
cookie_file=/tmp/jom-cookie.txt
for attempt in {1..20}; do
  if curl --cookie-jar "$cookie_file" --fail --silent http://127.0.0.1:8080/index.php >/tmp/jom-index.html; then break; fi
  sleep 0.25
done
grep -q 'Live communication' /tmp/jom-index.html
grep -q 'Indonesian' /tmp/jom-index.html
grep -q 'Thai' /tmp/jom-index.html
if grep -qi 'Tamil' /tmp/jom-index.html; then exit 1; fi
# Guest translation remains available, but member-only endpoints must reject guests.
for endpoint in 'api/glossary.php' 'api/assistance.php?scenario=medical&destination=Malaysia'; do
  status=$(curl --silent --show-error --output /tmp/jom-denied.json --write-out '%{http_code}' "http://127.0.0.1:8080/$endpoint")
  test "$status" = '403'
  php -r '$body=json_decode(file_get_contents("/tmp/jom-denied.json"),true,512,JSON_THROW_ON_ERROR); if (($body["ok"] ?? null) !== false) exit(1);'
done
if grep -q 'id="assistance"' /tmp/jom-index.html; then exit 1; fi
if grep -q 'Translation confidence: not provided by Google' assets/js/core.js; then
  echo 'FAIL: unavailable translation confidence must not be displayed' >&2
  exit 1
fi
# Exercise the translation endpoint without requiring external provider credentials.
curl --fail --silent -H 'Content-Type: application/json' -d '{"text":"Hello","from":"en","to":"en"}' http://127.0.0.1:8080/api/translate.php | php -r '$body=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); if (($body["ok"] ?? false) !== true || ($body["translation"] ?? "") !== "Hello") exit(1);'
csrf=$(sed -n 's/.*window.JOM={csrf:"\([^"]*\)".*/\1/p' /tmp/jom-index.html)
test -n "$csrf"
curl --cookie "$cookie_file" --fail --silent -H 'Content-Type: application/json' -d "{\"save_history\":true,\"analytics\":true,\"csrf\":\"$csrf\"}" http://127.0.0.1:8080/api/preferences.php | grep -q 'Guest privacy choices saved'
curl --cookie "$cookie_file" --fail --silent -H 'Content-Type: application/json' -d "{\"event_type\":\"translation\",\"language_code\":\"id\",\"scenario\":\"restaurant\",\"csrf\":\"$csrf\"}" http://127.0.0.1:8080/api/analytics.php | grep -q '"recorded":true'
echo 'PASS: guest HTTP smoke checks'
