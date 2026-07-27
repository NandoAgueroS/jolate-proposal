#!/bin/bash
# Manual verification tests for /backend/procesar-envio.php
# Requires a web server with PHP 5.3+ and valid SMTP or mail catcher (MailHog/smtp4dev).

SCRIPT_DIR="$(dirname "$0")"
BACKEND="http://localhost/backend/procesar-envio.php"
TMP=$(mktemp -d)

# Real minimal PDF — finfo recognizes it as application/pdf
cat > "$TMP/valid.pdf" <<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>
endobj
xref
0 4
0000000000 65535 f
0000000009 00000 n
0000000056 00000 n
0000000105 00000 n
trailer
<< /Size 4 /Root 1 0 R >>
startxref
164
%%EOF
PDF

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
tail -n 3 "$SCRIPT_DIR/logs/error.log"

echo "=== 5.6 Verify uploads .htaccess ==="
grep -q "Require all denied" "$SCRIPT_DIR/uploads/.htaccess" && echo "PASS" || echo "FAIL"

rm -rf "$TMP"