#!/usr/bin/env bash
# Exemples cURL (lecture seule). Usage : GSM_USER=... GSM_KEY=... bash examples/curl_examples.sh
set -euo pipefail
: "${GSM_USER:?Définissez GSM_USER}" "${GSM_KEY:?Définissez GSM_KEY}"
DHRU=https://gsmdeblocageserveur.com/public/api/index.php
REST=https://gsmdeblocageserveur.com/api.php

echo "== Dhru : accountinfo"
curl -s -X POST "$DHRU" --data-urlencode "username=$GSM_USER" --data-urlencode "apiaccesskey=$GSM_KEY" -d action=accountinfo -d requestformat=JSON; echo

echo "== Dhru : détail du service 123"
curl -s -X POST "$DHRU" --data-urlencode "username=$GSM_USER" --data-urlencode "apiaccesskey=$GSM_KEY" -d action=getimeiservicedetails -d requestformat=JSON \
  --data-urlencode "parameters=<PARAMETERS><ID>123</ID></PARAMETERS>"; echo

echo "== REST : solde"
curl -s "$REST?action=balance" -H "X-API-Key: $GSM_KEY"; echo
