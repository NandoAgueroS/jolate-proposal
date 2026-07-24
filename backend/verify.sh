#!/bin/bash
# Manual verification tests for /backend/procesar-envio.php
# Requires a web server with PHP 5.3+ and valid SMTP or mail catcher (MailHog/smtp4dev).

BACKEND="http://localhost/backend/procesar-envio.php"
TMP=$(mktemp -d)
dd if=/dev/urandom of="$TMP/valid.pdf" bs=1024 count=1 2>/dev/null
echo "not a pdf" > "$TMP/invalid.txt"
dd if=/dev/urandom of="$TMP/oversized.pdf" bs=1M count=16 2>/dev/null

echo "=== 5.1 Valid submission (expect HTTP 200) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -F "nombre=Dr. Test" \
  -F "institucion=Universidad Test" \
  -F "email=test@universidad.edu" \
  -F "eje_tematico=Teoría de Juegos" \
  -F "archivo=@$TMP/valid.pdf" \
  -F "website=" "$BACKEND"

echo "=== 5.2 Missing field (expect HTTP 422) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -F "nombre=" -F "institucion=Universidad Test" \
  -F "email=test@universidad.edu" \
  -F "eje_tematico=Teoría de Juegos" \
  -F "archivo=@$TMP/valid.pdf" "$BACKEND"

echo "=== 5.3 Non-PDF (expect HTTP 422) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -F "nombre=Dr. Test" -F "institucion=Universidad Test" \
  -F "email=test@universidad.edu" \
  -F "eje_tematico=Teoría de Juegos" \
  -F "archivo=@$TMP/invalid.txt" "$BACKEND"

echo "=== 5.4 Oversized file (expect HTTP 422) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -F "nombre=Dr. Test" -F "institucion=Universidad Test" \
  -F "email=test@universidad.edu" \
  -F "eje_tematico=Teoría de Juegos" \
  -F "archivo=@$TMP/oversized.pdf" "$BACKEND"

echo "=== 5.5 Check error.log ==="
tail -n 3 /home/nando/Developer/ulp/jolate/jolate-proposal/backend/logs/error.log

echo "=== 5.6 Verify uploads .htaccess ==="
grep -q "Require all denied" /home/nando/Developer/ulp/jolate/jolate-proposal/backend/uploads/.htaccess && echo "PASS" || echo "FAIL"

rm -rf "$TMP"
