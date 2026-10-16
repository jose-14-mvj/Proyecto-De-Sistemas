/**
 * MigraSense · envío de correos desde Gmail (Google Apps Script)
 *
 * Laravel (Render) llama a esta "aplicación web" por HTTPS y el correo sale
 * DE VERDAD desde la cuenta de Gmail dueña del script (Gmail sí lo acepta).
 *
 * La clave secreta NO va en este código: se guarda en
 * Configuración del proyecto (⚙️) → Propiedades del script → SECRETO
 * y debe ser igual a CORREO_APPSSCRIPT_SECRETO en Render.
 *
 * Al cambiar este código: Implementar → Gestionar implementaciones → ✏️ editar →
 * Versión: "Nueva versión" → Implementar. Así la URL NO cambia.
 */
function doPost(e) {
  try {
    var d = JSON.parse(e.postData.contents);
    var secreto = PropertiesService.getScriptProperties().getProperty('SECRETO');

    if (!secreto || d.secreto !== secreto) return responder({ ok: false, error: 'secreto incorrecto' });
    if (!d.para || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.para)) return responder({ ok: false, error: 'destinatario no válido' });

    // Adjuntos opcionales (el PDF del resumen semanal): [{nombre, tipo, base64}]
    var adjuntos = (d.adjuntos || []).slice(0, 3).map(function (a) {
      return Utilities.newBlob(Utilities.base64Decode(String(a.base64 || '')), String(a.tipo || 'application/pdf'), String(a.nombre || 'adjunto.pdf'));
    });

    var opciones = { htmlBody: String(d.html || ''), name: String(d.nombre || 'MigraSense') };
    if (adjuntos.length) opciones.attachments = adjuntos;
    MailApp.sendEmail(d.para, String(d.asunto || 'MigraSense'), String(d.texto || ''), opciones);
    return responder({ ok: true });
  } catch (err) {
    return responder({ ok: false, error: String(err && err.message || err).slice(0, 150) });
  }
}

function responder(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

/** Ejecútala UNA vez desde el editor (botón ▶ Ejecutar) para dar permiso de enviar correos. */
function autorizar() {
  Logger.log('Correos que aún puedes enviar hoy: ' + MailApp.getRemainingDailyQuota());
}
