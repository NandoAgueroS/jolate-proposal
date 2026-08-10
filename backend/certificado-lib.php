<?php
/**
 * JOLATE 2026 — Certificado de participación.
 *
 * Generación de PDF con FPDF 1.9 (backend/vendor/fpdf/fpdf.php).
 * FPDF no requiere extensiones extra (zlib/mbstring/gd opcionales);
 * el logo se incrusta como JPEG (soporte nativo sin GD).
 *
 * Provee:
 *   certificado_dni_valido(string)      — valida formato de DNI
 *   certificado_cache_dir(string)       — carpeta de caché por DNI
 *   certificado_cache_path(string,int)  — ruta del PDF cacheado
 *   certificado_existe(string,int)      — ¿ya fue generado?
 *   certificado_guardar(string,int,str) — escritura atómica del caché
 *   certificado_pdf(array)              — devuelve el PDF en bytes
 *
 * La carpeta de caché vive en backend/certificados/{dni}/{id}.pdf,
 * protegida por .htaccess (sin acceso HTTP directo).
 */

require __DIR__ . '/vendor/fpdf/fpdf.php';

const CERT_COLOR_PRIMARY = [5, 92, 98];      // #055c62
const CERT_COLOR_ACCENT  = [17, 176, 188];   // #11b0bc
const CERT_COLOR_TINT    = [203, 227, 230];  // #cbe3e6
const CERT_COLOR_TEXT    = [4, 60, 65];      // #043c41

/**
 * Valida el DNI con el mismo criterio que procesar-envio.php.
 */
function certificado_dni_valido(string $dni): bool {
    return preg_match('/^[A-Za-z0-9]{5,20}$/', $dni) === 1;
}

/**
 * Carpeta de caché para un DNI.
 */
function certificado_cache_dir(string $dni): string {
    global $config;
    $base = rtrim((string) ($config['certificado_dir'] ?? __DIR__ . '/certificados/'), '/');
    return $base . '/' . $dni;
}

/**
 * Ruta del PDF cacheado para un registro (id) de un DNI.
 */
function certificado_cache_path(string $dni, int $id): string {
    return certificado_cache_dir($dni) . '/' . $id . '.pdf';
}

/**
 * Indica si el certificado ya fue generado y está cacheado.
 */
function certificado_existe(string $dni, int $id): bool {
    $ruta = certificado_cache_path($dni, $id);
    return is_file($ruta) && filesize($ruta) > 0;
}

/**
 * Guarda el certificado de forma atómica (temp + rename) para que dos
 * peticiones simultáneas no corrompan el archivo.
 */
function certificado_guardar(string $dni, int $id, string $contenido): bool {
    $dir = certificado_cache_dir($dni);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = $dir . '/.' . $id . '.tmp' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $contenido) === false) {
        return false;
    }
    if (!@rename($tmp, certificado_cache_path($dni, $id))) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Convierte UTF-8 → CP1252 (encoding de las fuentes core de FPDF).
 * Sin mbstring: iconv es parte del core de PHP.
 */
function certificado_encode(string $s): string {
    if (function_exists('iconv')) {
        $res = iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($res !== false) {
            return $res;
        }
    }
    if (function_exists('mb_convert_encoding')) {
        $res = mb_convert_encoding($s, 'CP1252', 'UTF-8');
        if ($res !== false) {
            return $res;
        }
    }
    return preg_replace('/[^\x00-\xFF]/', '', $s) ?? '';
}

/**
 * Mayúsculas sobre CP1252 (cubre á é í ó ú ñ ç à è ì ò ù ü ý).
 */
function certificado_mayusculas(string $s): string {
    $out = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $o = ord($s[$i]);
        if ($o >= 0x61 && $o <= 0x7A) {
            $out .= chr($o - 0x20);
        } elseif ($o >= 0xE0 && $o <= 0xFF && $o !== 0xF7) {
            $out .= chr($o - 0x20);
        } else {
            $out .= $s[$i];
        }
    }
    return $out;
}

/**
 * Construye el certificado (A4 horizontal) y devuelve el PDF en bytes.
 *
 * @param array $reg Registro de `jolate_inscriptos` JOIN `jolate_tipo_inscripto`
 *                   con las claves: id, dni, nombre, titulo_ponencia,
 *                   eje_tematico, rol ('Expositor'|'Asistente').
 * @return string
 */
function certificado_pdf(array $reg): string {
    [$pr, $pg, $pb] = CERT_COLOR_PRIMARY;
    [$ar, $ag, $ab] = CERT_COLOR_ACCENT;
    [$tr, $tg, $tb] = CERT_COLOR_TINT;
    [$xr, $xg, $xb] = CERT_COLOR_TEXT;

    $logo = __DIR__ . '/assets/certificado-logo.jpg';

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(18, 18, 18);
    $pdf->AddPage();

    $pdf->SetTitle('Certificado de participación — XXV JOLATE', true);
    $pdf->SetAuthor('Comité Organizador — XXV JOLATE', true);
    $pdf->SetCreator('FPDF', true);

    // ── Marco doble ─────────────────────────────────────────────
    $pdf->SetDrawColor($tr, $tg, $tb);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(12, 12, 273, 186);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect(15, 15, 267, 180);

    // ── Logo (JPEG, fondo blanco) ───────────────────────────────
    $logoW = 60;
    $logoH = $logoW * 205 / 1104; // proporción 1104×205 del archivo
    $pdf->Image($logo, (297 - $logoW) / 2, 26, $logoW, $logoH);

    // ── Subtítulo del evento ────────────────────────────────────
    $pdf->SetY(42);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor($pr, $pg, $pb);
    $pdf->Cell(0, 6, certificado_encode('Jornadas Latinoamericanas de Teoría Económica'), 0, 1, 'C');

    // ── Regla de acento ─────────────────────────────────────────
    $pdf->SetFillColor($ar, $ag, $ab);
    $pdf->Rect(113.5, 50, 70, 0.9, 'F');

    // ── Título ──────────────────────────────────────────────────
    $pdf->SetY(57);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetTextColor($pr, $pg, $pb);
    $pdf->Cell(0, 9, certificado_encode('CERTIFICADO DE PARTICIPACIÓN'), 0, 1, 'C');

    // ── Cuerpo ──────────────────────────────────────────────────
    $pdf->SetY(70);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->SetTextColor($xr, $xg, $xb);
    $pdf->Cell(0, 6, certificado_encode('Se certifica que'), 0, 1, 'C');

    // Nombre en mayúsculas, tamaño ajustado a la longitud.
    $nombre = certificado_mayusculas(certificado_encode($reg['nombre']));
    $tam = 24;
    $pdf->SetFont('Helvetica', 'B', $tam);
    while ($tam > 16 && $pdf->GetStringWidth($nombre) > 200) {
        $tam -= 2;
        $pdf->SetFont('Helvetica', 'B', $tam);
    }
    $pdf->SetTextColor($pr, $pg, $pb);
    $pdf->SetX(28.5);
    $pdf->Cell(240, 14, $nombre, 0, 1, 'C');

    $pdf->SetTextColor($xr, $xg, $xb);
    $pdf->SetFont('Helvetica', '', 12);

    if (($reg['rol'] ?? '') === 'Expositor') {
        $detalle = 'en calidad de Expositor, presentando la ponencia «'
                 . trim($reg['titulo_ponencia'] ?? '')
                 . '», en el eje temático «' . trim($reg['eje_tematico'] ?? '') . '».';
    } else {
        $detalle = 'en calidad de Asistente.';
    }

    $pdf->SetY(94);
    $pdf->SetX(28.5);
    $pdf->MultiCell(240, 6.3, certificado_encode($detalle), 0, 'C');

    $evento = 'en las XXV Jornadas Latinoamericanas de Teoría Económica, '
            . 'realizadas del 28 al 30 de octubre de 2026 en la ciudad de San Luis, Argentina.';
    $pdf->SetX(28.5);
    $pdf->MultiCell(240, 6.3, certificado_encode($evento), 0, 'C');

    // ── Pie ─────────────────────────────────────────────────────
    $pdf->SetY(156);
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(0, 6, certificado_encode('San Luis, Argentina · 30 de octubre de 2026'), 0, 1, 'C');

    $pdf->SetDrawColor($ar, $ag, $ab);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(118.5, 168, 178.5, 168);
    $pdf->SetY(171);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor($pr, $pg, $pb);
    $pdf->Cell(0, 6, certificado_encode('Comité Organizador'), 0, 1, 'C');

    return $pdf->Output('S');
}
