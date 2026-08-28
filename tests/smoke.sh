#!/usr/bin/env bash
set -euo pipefail
php -S 127.0.0.1:8080 -t . > /tmp/jomcommunicate-php.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT
for attempt in {1..20}; do
  if curl --fail --silent http://127.0.0.1:8080/index.php >/tmp/jom-index.html; then break; fi
  sleep 0.25
done
grep -q 'Real-time tourism communication' /tmp/jom-index.html
grep -q 'Indonesian' /tmp/jom-index.html
grep -q 'Thai' /tmp/jom-index.html
if grep -qi 'Tamil' /tmp/jom-index.html; then exit 1; fi
curl --fail --silent 'http://127.0.0.1:8080/api/glossary.php' | grep -q 'Tapau'
curl --fail --silent 'http://127.0.0.1:8080/api/assistance.php?scenario=medical&destination=Malaysia' | grep -q 'doctor'
