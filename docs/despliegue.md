# Despliegue

| Servicio | Plataforma | Dirección | Se actualiza |
|---|---|---|---|
| Frontend | Cloudflare Pages | https://migrasense.pages.dev/ | Al hacer push a `main` del repo del frontend (automático) |
| Frontend (respaldo) | GitHub Pages | https://miguelhuanca676-bot.github.io/migrasense/ | Igual, con el mismo push |
| API | Render (Docker) | https://migrasence-api.onrender.com/api | Al hacer push a `main` (Auto-Deploy) o con *Manual Deploy* |
| Machine learning | Render (Docker) | https://migrasence-ml.onrender.com | Al hacer push al repo `migrasence-ml` |
| Base de datos | Aiven (MySQL 8.4) | privado | Con migraciones (ver abajo) |
| Despertador | cron-job.org | — | Llama a `/api/tareas/recordatorios` cada 15 min de 06:00 a 23:45 |

## Orden para publicar un cambio

Este orden evita que la app pida algo que el servidor todavía no sabe hacer:

1. Correr las pruebas: `php artisan test`.
2. Si hay migraciones nuevas, aplicarlas en Aiven **antes** de subir el código:
   `php artisan migrate --database=mysql_admin --force`.
3. Subir el backend (`git push`) y esperar a que Render diga **Live**.
   Para comprobar que ya está la versión nueva se puede abrir una ruta nueva en el navegador: si responde
   `Unauthenticated` (y no 404), ya está publicada.
4. Subir el frontend.
5. Probar en la página publicada con **Ctrl+F5**.

## Cloudflare Pages: configuración del frontend

- Proyecto **migrasense** en Cloudflare (Workers & Pages → Pages), conectado al repo
  `miguelhuanca676-bot/migrasense`, rama `main`.
- Sin paso de compilación: *Framework preset* **None**, *Build command* vacío, *Build output directory* vacío
  (se publica la raíz del repo tal cual).
- Cada `git push` a `main` publica solo en 1–2 minutos. El historial está en Cloudflare → proyecto → *Deployments*.
- GitHub Pages sigue activo con el mismo repo, como dirección de respaldo.

### Si se cambia o agrega una dirección del frontend

La API solo acepta llamadas de las direcciones que conoce. En Render (`migrasence-api` → *Environment*):

- `CORS_ALLOWED_ORIGINS`: todas las direcciones separadas por comas, sin `/` al final. Hoy:
  `https://miguelhuanca676-bot.github.io,https://migrasense.pages.dev`
- `FRONTEND_URL`: la dirección principal (se usa en los botones de los correos): `https://migrasense.pages.dev`

Si falta la dirección en `CORS_ALLOWED_ORIGINS`, la página abre pero no puede iniciar sesión. Cada persona debe
iniciar sesión y aceptar las notificaciones de nuevo en una dirección nueva (el navegador guarda la sesión y
las notificaciones por dirección).

## Render: configuración de la API

- **Root Directory:** `Backend_Laravel`
- **Runtime:** Docker (usa `Backend_Laravel/Dockerfile`: PHP 8.2 + Apache, `composer install --no-dev`,
  `config:cache` y `route:cache` al arrancar).
- **Secret File:** `ca.pem` (certificado de Aiven). Al arrancar, `docker/inicio.sh` lo copia a un lugar que
  Apache puede leer.
- **Variables de entorno:** las mismas de `.env.example`, con `APP_ENV=production`, `APP_DEBUG=false` y
  `TRUSTED_PROXIES=*`.

## Plan gratuito: qué tener en cuenta

- Render duerme los servicios tras 15 minutos sin uso; la primera petición puede tardar hasta un minuto.
  El frontend muestra "Conectando con el servidor…" y reintenta solo.
- El plan da 750 horas al mes; por eso el despertador solo funciona de 06:00 a 23:45.
- Aiven gratuito no hace respaldos automáticos largos; está pendiente un respaldo programado (ver issues).
