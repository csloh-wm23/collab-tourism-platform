#!/usr/bin/env bash
set -euo pipefail
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
curl --fail --silent 'http://127.0.0.1:8080/api/glossary.php' | grep -q 'Tapau'
curl --fail --silent 'http://127.0.0.1:8080/api/assistance.php?scenario=medical&destination=Malaysia' | grep -q 'doctor'
grep -q 'Phrase language' /tmp/jom-index.html
grep -q 'Translation confidence: not provided by Google' assets/js/core.js
csrf=$(sed -n 's/.*window.JOM={csrf:"\([^"]*\)".*/\1/p' /tmp/jom-index.html)
test -n "$csrf"
curl --cookie "$cookie_file" --fail --silent -H 'Content-Type: application/json' -d "{\"save_history\":true,\"analytics\":true,\"csrf\":\"$csrf\"}" http://127.0.0.1:8080/api/preferences.php | grep -q 'Guest privacy choices saved'
curl --cookie "$cookie_file" --fail --silent -H 'Content-Type: application/json' -d "{\"event_type\":\"translation\",\"language_code\":\"id\",\"scenario\":\"restaurant\",\"csrf\":\"$csrf\"}" http://127.0.0.1:8080/api/analytics.php | grep -q '"recorded":true'
