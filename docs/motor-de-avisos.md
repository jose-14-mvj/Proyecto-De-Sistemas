# Motor de avisos

Los tres avisos de MigraSense llegan aunque la app esté cerrada **y aunque la API en Render esté dormida o caída**:

| Aviso | Cuándo sale | Qué llega |
| --- | --- | --- |
| Recordatorio diario | A la hora elegida en Perfil (si ese día no registró nada) | Notificación «¿Cómo te fue hoy?». Al tocarla abre el registro de síntomas prodrómicos. Sin dispositivos: correo. |
| Resumen semanal | El día y la hora elegidos | Notificación + correo con el PDF de los últimos 7 días. Si el proveedor rechaza el adjunto, el correo sale igual, sin PDF. |
| Alerta de posible migraña | Cuando el modelo da 70 % o más y hay una señal reciente | Notificación con la **probabilidad** y **en cuántas horas** podría empezar. Sin dispositivos: correo. |

## Cómo funciona

```
GitHub Actions (cada 5 min)  ──►  php artisan migrasense:enviar-recordatorios  ──►  Aiven (MySQL)
   repo miguelhuanca676-bot/migrasense                │
   .github/workflows/avisos.yml                       ├──►  servicios de push (Chrome, Firefox, Safari)
                                                      ├──►  correo (Apps Script / Brevo)
                                                      └──►  ML en Render (solo para la alerta; lo despierta y reintenta)
```

- El programador de GitHub descarga el backend (rama `main`) y ejecuta el comando **conectado directo a la base de datos**.
  No pasa por la API de Render ni por cron-job.org.
- Antes el único disparador era cron-job.org llamando a Render cada 15 minutos. Render gratis se duerme a los
  15 minutos sin visitas y tarda cerca de 1 minuto en despertar, mientras que cron-job.org espera como máximo 30 segundos.
  Es la causa más probable de que los avisos no salieran: la llamada se cortaba antes de que la API despertara.
- **Nunca se envía dos veces**: cada aviso se anota en `envio_recordatorio` antes de salir (clave única por persona, tipo y
  día/semana). Por eso el despertador antiguo puede seguir activo como respaldo sin duplicar nada.
- Si una vuelta se atrasa, no importa: el aviso sale mientras no hayan pasado más de 3 horas de la hora elegida
  (`RECORDATORIO_VENTANA_HORAS`).
- La alerta de migraña se recalcula para cada persona cada 3 horas (`ALERTA_CADA_HORAS`), o enseguida si registró algo
  nuevo. Máximo una alerta cada 12 horas. De 23:00 a 05:59 no se envían alertas (`ALERTA_SILENCIO=23-6`).
- Si el ML está dormido, la vuelta no falla: la alerta se reintenta sola en la siguiente.

## Qué sigue dependiendo de qué

| Para… | Hace falta |
| --- | --- |
| **Enviar** los 3 avisos | GitHub Actions + Aiven + el servicio de push del navegador. Nada más. |
| El PDF por correo | El proveedor de correo (Apps Script o Brevo). |
| La alerta de migraña | El ML en Render (el motor lo despierta; si no responde, reintenta en 5 min). |
| **Activar** los avisos en un dispositivo | La API en Render (una sola vez, al aceptar el permiso con la app abierta). |

Límite real de la tecnología: una página web no puede programar por sí sola una notificación para más tarde; siempre
tiene que haber un servidor que la envíe a esa hora. Ese servidor ahora es GitHub, que no se duerme.

## Puesta en marcha (una sola vez)

En el repo **miguelhuanca676-bot/migrasense** → *Settings → Secrets and variables → Actions → New repository secret*:

1. `MIGRASENSE_ENV`: el contenido del `.env` del backend con los valores de producción (los mismos de Render →
   Environment). Como mínimo:

   ```
   DB_CONNECTION=mysql
   DB_HOST=…            DB_PORT=…            DB_DATABASE=migrasense
   DB_USERNAME=migrasense_app               DB_PASSWORD="…"
   VAPID_PUBLIC_KEY=…   VAPID_PRIVATE_KEY="…"   VAPID_SUBJECT="mailto:…"
   CORREO_APPSSCRIPT_URL=…   CORREO_APPSSCRIPT_SECRETO="…"      (y/o BREVO_API_KEY + CORREO_REMITENTE)
   CORREO_REMITENTE_NOMBRE=MigraSense
   FRONTEND_URL=https://migrasense.pages.dev
   ML_URL=https://migrasence-ml.onrender.com   ML_API_KEY="…"
   ```

   Una variable por línea. **Las claves VAPID deben ser exactamente las de Render**: si son otras, los dispositivos ya
   suscritos rechazan los avisos.
2. `AIVEN_CA_PEM`: el contenido completo del archivo `ca.pem` de Aiven (desde `-----BEGIN CERTIFICATE-----`).

Mientras falten los secretos el motor no hace nada y no marca error.

## Comprobar que funciona

En el repo `migrasense` → pestaña **Actions** → **Motor de avisos** → **Run workflow**:

- `probar-avisos` + el correo de una cuenta: envía ahora mismo los 3 avisos de prueba a esa cuenta y dice, canal por
  canal, qué funcionó (cuántos dispositivos tiene la cuenta, si el correo con PDF fue aceptado, la probabilidad actual).
- `probar-correo` + un correo: envía un correo de prueba y dice qué proveedor lo envió o por qué falló.
- `enviar`: lo mismo que hace solo cada 5 minutos.

Cada vuelta deja un resumen, por ejemplo:
`[2026-11-13 20:05 Bolivia] Diarios enviados: 3 · Semanales: 0 · Omitidos (ya registró hoy): 1 · Errores: 0`

Los registros de Actions son públicos: la salida nunca muestra nombres, correos ni claves.

En la PC, con el `.env` apuntando a Aiven: `php artisan migrasense:enviar-recordatorios --historial` muestra los últimos envíos.

## Cuidados

- GitHub **apaga los programadores de un repo que pasa 60 días sin ningún commit**. Si ocurre, avisa por correo y se
  reactiva en Actions → Motor de avisos → *Enable workflow*.
- GitHub no garantiza el minuto exacto: en horas de mucha carga una vuelta puede atrasarse unos minutos. Por eso corre
  cada 5 minutos y cada aviso tiene 3 horas de tolerancia.
- Si se cambian las claves VAPID o la contraseña de la base en Render, hay que actualizar también `MIGRASENSE_ENV`.
