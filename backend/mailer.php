<?php
/**
 * JOLATE 2026 — Email sending functions.
 *
 * Shared by procesar-envio.php (web) and send-pending-emails.php (cron).
 * Each function throws on SMTP failure; the caller handles retry logic.
 */

require __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/SMTP.php';
require __DIR__ . '/vendor/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// ── Email HTML templates ────────────────────────────────────────

function mailField($label, $valor) {
    return '<p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#055c62;">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:2px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#043c41;">'
        . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</p>';
}

function mailWrap($titulo, $contenido, $badge = '') {
    $badgeHtml = '';
    if ($badge !== '') {
        $badgeHtml = '<p style="margin:0 0 18px;"><span style="display:inline-block;background-color:#cbe3e6;color:#055c62;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;padding:4px 12px;border-radius:999px;">'
            . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span></p>';
    }
    return '<div style="background-color:#eef9fa;padding:24px;">'
        . '<div style="max-width:600px;width:100%;margin:0 auto;background-color:#ffffff;border:1px solid #cbe3e6;border-radius:12px;overflow:hidden;">'
        . '<div style="background-color:#055c62;padding:22px 28px;">'
        . '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#11b0bc;">XXV JOLATE · San Luis, Argentina</p>'
        . '<p style="margin:6px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:bold;color:#ffffff;">'
        . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
        . '<div style="padding:28px;">'
        . $badgeHtml
        . $contenido
        . '</div>'
        . '<div style="background-color:#eef9fa;border-top:1px solid #cbe3e6;padding:14px 28px;">'
        . '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#043c41;">JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica · San Luis, Argentina</p>'
        . '</div>'
        . '</div>'
        . '</div>';
}

// ── Email sending ───────────────────────────────────────────────

/**
 * Send participant confirmation email.
 *
 * @param array $config  Full config array from config.php
 * @param array $row     Row from jolate_inscriptos (all columns)
 * @param string|null $pdfPath  Absolute path to PDF, or null for Asistente
 * @throws Exception on SMTP failure
 */
function sendParticipantEmail(array $config, array $row, $pdfPath = null) {
    $nombre = $row['nombre'];
    $email  = $row['email'];
    $dni    = $row['dni'];
    $isExpositor = ((int) $row['id_tipo_inscripto'] === 1);

    $nombreSafe = preg_replace('/[\r\n]/', '', $nombre);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port       = $config['smtp']['port'];
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 30;

    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);
    $mail->addAddress($email, $nombreSafe);
    $mail->isHTML(true);

    if ($isExpositor) {
        $mail->addAttachment($pdfPath, 'ponencia-' . $dni . '.pdf');

        $mail->Subject = 'Confirmación de recepción de ponencia — JOLATE 2026';
        $mail->Body    = mailWrap(
            'Tu ponencia fue recibida correctamente',
            mailField('Nombre', $nombre)
            . mailField('Eje temático', $row['eje_tematico'])
            . mailField('Título de la ponencia', $row['titulo_ponencia'])
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">Tu ponencia se adjunta a este correo.</p>'
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">En breve el comité se pondrá en contacto.</p>',
            'Expositor'
        );
        $mail->AltBody = 'Tu ponencia fue recibida correctamente.' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Rol: Expositor' . "\n"
            . 'Eje: ' . $row['eje_tematico'] . "\n"
            . 'Título: ' . $row['titulo_ponencia'] . "\n"
            . 'Archivo: adjunto a este correo' . "\n"
            . 'En breve el comité se pondrá en contacto.';
    } else {
        $mail->Subject = 'Confirmación de inscripción — JOLATE 2026';
        $mail->Body    = mailWrap(
            'Tu inscripción fue recibida correctamente',
            mailField('Nombre', $nombre)
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">En breve el comité se pondrá en contacto.</p>',
            'Asistente'
        );
        $mail->AltBody = 'Tu inscripción fue recibida correctamente.' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Rol: Asistente' . "\n"
            . 'En breve el comité se pondrá en contacto.';
    }

    $mail->send();
}

/**
 * Send committee notification email (to all configured recipients).
 *
 * @param array $config  Full config array from config.php
 * @param array $row     Row from jolate_inscriptos (all columns)
 * @param string|null $pdfPath  Absolute path to PDF, or null for Asistente
 * @throws Exception on SMTP failure
 */
function sendCommitteeEmail(array $config, array $row, $pdfPath = null) {
    $nombre = $row['nombre'];
    $email  = $row['email'];
    $dni    = $row['dni'];
    $isExpositor = ((int) $row['id_tipo_inscripto'] === 1);

    $nombreSafe = preg_replace('/[\r\n]/', '', $nombre);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->SMTPSecure = $config['smtp']['encryption'];
    $mail->Port       = $config['smtp']['port'];
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 30;

    $mail->setFrom($config['smtp']['from_email'], $config['smtp']['from_name']);

    foreach ($config['committee_emails'] as $emailDestino) {
        $mail->addAddress($emailDestino);
    }
    $mail->addReplyTo($email, $nombreSafe);
    $mail->isHTML(true);

    if ($isExpositor) {
        $mail->addAttachment($pdfPath, 'ponencia-' . $dni . '.pdf');

        $mail->Subject = 'Nueva ponencia recibida: ' . $nombreSafe . ' (' . $row['eje_tematico'] . ')';
        $mail->Body    = mailWrap(
            'Nueva ponencia / resumen recibido',
            mailField('Nombre', $nombre)
            . mailField('DNI / Pasaporte', $dni)
            . mailField('Institución', $row['institucion'])
            . mailField('Correo', $email)
            . mailField('Eje temático', $row['eje_tematico'])
            . mailField('Título de la ponencia', $row['titulo_ponencia'])
            . '<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#043c41;">La ponencia se adjunta a este correo.</p>',
            'Expositor'
        );
        $mail->AltBody = 'Nueva ponencia recibida' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'DNI / Pasaporte: ' . $dni . "\n"
            . 'Institución: ' . $row['institucion'] . "\n"
            . 'Correo: ' . $email . "\n"
            . 'Rol: Expositor' . "\n"
            . 'Eje: ' . $row['eje_tematico'] . "\n"
            . 'Título: ' . $row['titulo_ponencia'] . "\n"
            . 'Archivo: adjunto a este correo';
    } else {
        $mail->Subject = 'Nueva inscripción: ' . $nombreSafe . ' (Asistente)';
        $mail->Body    = mailWrap(
            'Nueva inscripción',
            mailField('Nombre', $nombre)
            . mailField('Institución', $row['institucion'])
            . mailField('Correo', $email),
            'Asistente'
        );
        $mail->AltBody = 'Nueva inscripción' . "\n"
            . 'Nombre: ' . $nombre . "\n"
            . 'Institución: ' . $row['institucion'] . "\n"
            . 'Correo: ' . $email . "\n"
            . 'Rol: Asistente';
    }

    $mail->send();
}
