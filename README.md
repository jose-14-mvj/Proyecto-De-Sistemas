# MigraSense — Frontend (HTML + CSS + JS)

Frontend funcional del sistema web de registro de síntomas para personas
con migraña (proyecto "Los Backyardigans"). Inspirado en el flujo de
onboarding y registro de la app **Flo**, adaptado a migrañas.

No usa frameworks ni backend: todo el estado (usuario, respuestas del
cuestionario y registros) se guarda en `localStorage` del navegador, así
que puedes abrir `index.html` directamente y probar el flujo completo.

## Cómo probarlo

Ábrelo con un servidor local simple (recomendado, para que las rutas
funcionen igual que en producción):

```bash
cd MigraSense
python3 -m http.server 8000
# abre http://localhost:8000
```

También puedes abrir `index.html` haciendo doble clic, pero algunos
navegadores restringen ciertas cosas al abrir archivos con `file://`.

## Flujo de pantallas

```
index.html  →  login.html / register.html  →  onboarding.html (9 pasos)
                                                     ↓
                          dashboard.html  ⇄  registro.html  ⇄  historial.html
                                 ↕
                         perfil.html · consejos.html
```

- **index.html** — decide a dónde mandar al usuario según si ya inició
  sesión y si completó el cuestionario (usa `localStorage`).
- **login.html / register.html** — autenticación simulada, con
  validaciones reales de formulario (correo, contraseña, términos).
- **onboarding.html** — cuestionario de personalización de 9 pasos
  (ver detalle abajo). Barra de progreso, botón atrás y "Más tarde".
- **dashboard.html** — pantalla de inicio: predicción de riesgo (mock),
  accesos rápidos, banner de "Detectar señales", síntomas recientes y
  recordatorios.
- **registro.html** — formulario de registro con dos pestañas:
  *Síntomas prodrómicos* y *Episodio de migraña completo*.
- **historial.html** — lista filtrable de todos los registros, gráficos,
  calendario y **reporte en PDF** (periodo a elegir: 30 días, 3 meses,
  12 meses o todo; se descarga o se comparte). Conserva una versión `.txt`.
- **perfil.html** — edición de datos, resumen del perfil de migraña,
  **recordatorios con hora y día configurables** y cierre de sesión.
- **consejos.html** — tips generales de bienestar (contenido propio).

## Las 9 preguntas del onboarding

Diseñadas para que el sistema entienda mejor al paciente (inspirado en
la profundidad del onboarding de Flo, pero enfocado en migraña):

1. ¿Te han diagnosticado migraña anteriormente?
2. Rango de edad y sexo biológico (influyen en el patrón hormonal).
3. Frecuencia habitual de los episodios.
4. Duración habitual de un episodio.
5. Intensidad habitual del dolor (escala 1–10).
6. Disparadores frecuentes (estrés, sueño, alimentos, clima, pantallas,
   deshidratación, alcohol, olores, ejercicio, ruido, etc.).
7. Síntomas prodrómicos habituales (sensibilidad a luz/sonido, fatiga,
   dolor de cuello, antojos, cambios de humor, aura, náuseas, etc.).
8. Antecedentes familiares y uso de medicación preventiva.
9. Preferencias de recordatorio diario (activar/desactivar y horario).

Todas se guardan en `localStorage` (`ms_onboarding`) y se muestran luego
en el perfil y se usan para el cálculo del riesgo mostrado en el inicio.

## Estructura de carpetas

```
MigraSense/
├── index.html
├── login.html
├── register.html
├── onboarding.html
├── dashboard.html
├── registro.html
├── historial.html
├── perfil.html
├── consejos.html
├── css/
│   └── style.css        (sistema de diseño: colores, tipografía, componentes)
├── js/
│   ├── app.js            (utilidades compartidas, sesión, catálogos)
│   ├── auth.js           (login + registro)
│   ├── onboarding.js     (las 9 preguntas)
│   ├── dashboard.js
│   ├── registro.js
│   ├── historial.js
│   ├── perfil.js
│   ├── analisis.js       (riesgo y patrones)
│   ├── reporte-pdf.js    (genera el reporte PDF)
│   ├── recordatorios.js  (programación, notificaciones, .ics)
│   └── vendor/           (jsPDF + AutoTable, MIT; sin depender de CDN)
├── sw.js                 (Service Worker: muestra notificaciones)
├── manifest.webmanifest  (permite instalar la app en el celular)
└── assets/img/
    ├── logo.png          (logo recortado y con fondo transparente, 512px)
    ├── logo-256.png
    ├── logo-icon.png     (192px, para usar en tarjetas pequeñas)
    ├── logo-96.png
    ├── favicon-64.png
    └── favicon-32.png    (favicon del sitio)
```

## Mapeo con el Product Backlog del informe

| Historia de usuario | Dónde está implementada |
|---|---|
| HU-01 Registrar usuario | `register.html` |
| HU-02 Iniciar sesión | `login.html` |
| HU-03 Configurar info personal/médica | `onboarding.html` (9 pasos) |
| HU-04 Registrar síntomas prodrómicos | `registro.html` (pestaña 1) |
| HU-05 Registrar migraña (dolor, duración, horario, síntomas) | `registro.html` (pestaña 2) |
| HU-06 Mostrar síntomas y episodios registrados | `historial.html` |
| HU-07 Panel de información de síntomas prodrómicos | `dashboard.html` (tarjeta de predicción + síntomas recientes) |
| HU-08 Notificaciones preventivas | Banner "Detectar señales" + tarjeta de riesgo en `dashboard.html` |
| HU-09 Recordatorios diarios | `js/recordatorios.js` (lógica), `perfil.html` (hora/día), `dashboard.html` (toggles + aviso), `sw.js` (notificaciones) |
| HU-10 Generar reporte | Botón de reporte en `historial.html` → PDF con `js/reporte-pdf.js` |
| HU-11 Editar perfil | `perfil.html` |
| HU-12 Cerrar sesión | Botón en `perfil.html` |

## Siguientes pasos sugeridos para el equipo

- Conectar los formularios a la base de datos real (reemplazar
  `localStorage` por llamadas a la API/backend de Ariel y José Miguel).
- Implementar el modelo predictivo real detrás del botón
  "Detectar señales de migraña" (hoy es una simulación basada en la
  cantidad de registros recientes).
- Web Push con backend para avisar con la app totalmente cerrada (ver
  "Recordatorios"). `sw.js` ya trae el manejador `push`.
- Revisión de accesibilidad (contraste, navegación por teclado) antes
  de la entrega final.

## Reporte en PDF

`historial.html` → botón del calendario (arriba a la derecha) → elegir
periodo → **Descargar PDF**. Se genera en el navegador (ningún dato sale
del dispositivo) con `js/reporte-pdf.js`. Contiene: datos del paciente,
resumen del periodo, gráficos (episodios por semana/mes e intensidad),
patrones detectados (usa `MSAnalisis`), desencadenantes y síntomas más
frecuentes, perfil del cuestionario, tabla completa de registros y un
espacio para observaciones del médico. Las librerías se cargan solo al
abrir el reporte. En celulares con "Compartir" del sistema aparece
también el botón **Compartir…**.

## Recordatorios

Configuración en `perfil.html → Recordatorios` (hora del aviso diario, día
y hora del resumen semanal). Qué hace cada capa:

| Capa | Funciona con la app… | Requiere |
|---|---|---|
| Aviso dentro de la app (banner: *Registrar ahora / En 1 hora / ×*) | abierta | nada |
| Notificación del dispositivo | abierta, en segundo plano o instalada | permiso del navegador; `https` o `localhost` |
| Calendario `.ics` (**Añadir a mi calendario**) | **cerrada** | importar el archivo una vez |
| Web Push (pendiente) | cerrada | backend con claves VAPID |

Detalles: el aviso diario no se muestra si ya registraste algo hoy; el
mensaje cambia según el riesgo estimado; el banner se puede posponer 1 h;
la notificación del sistema se envía una sola vez al día. En iPhone, las
notificaciones solo existen si la app se instala en la pantalla de inicio
(iOS 16.4+). El Service Worker exige `https` o `localhost`
(`python3 -m http.server 8000` funciona; abrir con `file://` no).
