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
- **historial.html** — lista filtrable de todos los registros, con
  generación y descarga de un reporte en `.txt`.
- **perfil.html** — edición de datos, resumen del perfil de migraña,
  toggles de notificaciones y cierre de sesión.
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
│   └── perfil.js
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
| HU-09 Recordatorios diarios | Toggles en `dashboard.html`, `onboarding.html` (paso 9) y `perfil.html` |
| HU-10 Generar reporte | Botón de reporte en `historial.html` (genera y descarga `.txt`) |
| HU-11 Editar perfil | `perfil.html` |
| HU-12 Cerrar sesión | Botón en `perfil.html` |

## Siguientes pasos sugeridos para el equipo

- Conectar los formularios a la base de datos real (reemplazar
  `localStorage` por llamadas a la API/backend de Ariel y José Miguel).
- Implementar el modelo predictivo real detrás del botón
  "Detectar señales de migraña" (hoy es una simulación basada en la
  cantidad de registros recientes).
- Añadir notificaciones push/email reales para los recordatorios.
- Revisión de accesibilidad (contraste, navegación por teclado) antes
  de la entrega final.
