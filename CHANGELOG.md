# Historial de versiones

Todas las versiones de MigraSense, de la más nueva a la más antigua. Usamos
[versionado semántico](https://semver.org/lang/es/): `MAYOR.MENOR.PARCHE`.
Cada versión tiene su etiqueta (`git tag`) y su *release* en GitHub.

## [Sin publicar]

### Agregado
- Integración continua con **GitHub Actions**: las pruebas del backend corren solas en cada push y pull request
  (insignia en el README).
- 8 pruebas nuevas (114 en total): validación de la hora de fin al terminar una migraña y manejo de errores
  de la base de datos.

### Refactorizado
- `RegistroController::terminar()` ya no copia la regla de fecha: usa `TerminarEpisodioRequest` con el trait
  `ValidaFechaRegistro`, igual que crear y editar. La copia se había quedado sin la regla de «fecha demasiado
  antigua».
- El manejo de errores de la base de datos (SQLSTATE 45000 → 422) estaba copiado en 5 controladores: ahora vive
  en `Controller::conReglasDeBd()`. La respuesta 404 se unificó en `Controller::noEncontrado()`.

### Cambiado
- La app ahora se publica en **Cloudflare Pages**: https://migrasense.pages.dev. La dirección de GitHub Pages
  sigue funcionando como respaldo.
- Render: `CORS_ALLOWED_ORIGINS` acepta las dos direcciones y `FRONTEND_URL` apunta a la nueva (los enlaces
  de los correos llevan a Cloudflare Pages).

## [1.6.0] — 2026-10-21 · Repositorio oficial y documentación

### Agregado
- README nuevo, documentación técnica en `docs/` y manual de usuario con capturas.
- `CONTRIBUTING.md` con el flujo de ramas, commits, pull requests y versiones.
- Plantillas de issues (tarea y error) y de pull request.
- Issues, hitos y tablero de tareas en GitHub con todo lo hecho y lo pendiente.

### Cambiado
- El frontend de este repositorio se sincronizó con la versión publicada en GitHub Pages (antes había una
  copia antigua que guardaba todo en `localStorage`).
- `Backend_Laravel/README.md` ahora explica nuestro backend en lugar del texto por defecto de Laravel.

## [1.5.0] — 2026-10-21 · Migraña en curso y aura

### Agregado
- Al registrar una migraña: opciones **Ahora mismo** / **Elegir otra hora** y **Aún en curso**.
- Tarjeta **"La migraña terminó"** en Inicio; guarda la hora exacta de fin y calcula la duración. Pasadas
  72 h pregunta a qué hora terminó realmente.
- Pregunta **"¿Es una migraña con aura?"** (Sí / No / No estoy seguro/a).
- Fecha (además de la hora) al registrar un síntoma prodrómico con "Elegir otra hora".
- Endpoints `GET /api/registros/en-curso` y `POST /api/registros/episodio/{id}/terminar`.

### Cambiado
- "Síntomas presentes" pasa a ser "Síntomas prodrómicos que detectaste"; "Auras" deja de ser un síntoma y
  los registros antiguos que lo tenían se convierten en "con aura = sí".
- Botón de Inicio: "Registrar síntoma prodrómico · Algún síntoma extraño que notes".

## [1.4.0] — 2026-10-16 · Recordatorios reales

### Agregado
- Notificaciones push (Web Push con VAPID) que llegan con la app cerrada.
- Resumen semanal con reporte PDF por correo.
- Endpoint de tareas programadas protegido con clave, despertado por cron-job.org.

## [1.3.0] — 2026-10-16 · Correcciones de uso y rediseño

### Agregado
- Edición completa de registros desde el mismo formulario.
- Opción "Ningún síntoma".

### Cambiado
- Rediseño de la interfaz pensado para personas sensibles a la luz (menú lateral en PC, barra inferior en
  celular, sin animaciones infinitas).

## [1.2.0] — 2026-10-16 · Memoria del modelo de ML

### Agregado
- El modelo guarda su historial de entrenamiento y métricas en la base de datos.
- Panel local para revisar la memoria del modelo (`php artisan migrasense:panel-ml`).
- Reentrenamiento automático cuando hay suficientes registros nuevos.

## [1.1.0] — 2026-10-14 · Recuperar contraseña

### Agregado
- "¿Olvidaste tu contraseña?" con código por correo (Apps Script de Gmail, con Brevo como respaldo).
- Límite de 5 códigos por día y 1 por minuto por cuenta.

## [1.0.0] — 2026-10-13 · Primera versión completa en la nube

### Agregado
- Base de datos en Aiven (MySQL 8.4) con conexión cifrada (TLS) y usuario de mínimos privilegios.
- API completa: perfil, cuenta, registros, recordatorios, notificaciones, estadísticas y reportes.
- Servicio de machine learning (FastAPI + scikit-learn) entrenado con datos simulados a partir de
  literatura médica; endpoint `GET /api/prediccion`.
- Despliegue del backend y del ML en Render con Docker.
- Seguridad: cabeceras HTTP, CORS, límites de intentos, contraseñas con Argon2id.
- Pruebas automáticas con PHPUnit.

## [0.4.0] — 2026-10-12 · Backend en Laravel

### Agregado
- Autenticación con Laravel Sanctum (tokens) y contraseñas con Argon2id.
- Controladores, servicios y validaciones para registro, inicio de sesión y perfil.

## [0.3.0] — 2026-10-09 · Base de datos y primera API

### Agregado
- Scripts SQL: tablas, catálogos, vistas, procedimientos almacenados y triggers.
- Primera API en PHP puro (registro, login, sesión y perfil).
- Proyecto Laravel inicial.

## [0.2.0] — 2026-10-08 · Reportes y diseño adaptable

### Agregado
- Descarga del historial en PDF con gráficos y logo.
- Notificaciones programadas y aviso de posible episodio.
- Diseño adaptable a celular con navegación fija.

## [0.1.0] — 2026-09-29 · Prototipo del frontend

### Agregado
- Pantallas de inicio de sesión, registro, cuestionario inicial, inicio, registro de síntomas,
  historial, consejos y perfil.
- Predicción por reglas y análisis de patrones con datos guardados en el navegador.
