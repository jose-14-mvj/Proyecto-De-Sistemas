# Instalación local

Esta guía es para levantar MigraSense completo en tu computadora. Está probada en Windows con XAMPP y
PowerShell, y en Linux.

## Requisitos

| Programa | Versión | Nota |
|---|---|---|
| PHP | 8.2 | En XAMPP activa `extension=pdo_mysql`, `extension=pdo_sqlite`, `extension=sqlite3` y `extension=openssl` en `php.ini` |
| Composer | 2.x | |
| MySQL | 8.x (o MariaDB de XAMPP) | Para producción usamos MySQL 8.4 en Aiven |
| Git | cualquiera | |
| Python | 3.10+ | Solo para servir el frontend o correr el servicio de ML |

## 1. Clonar e instalar dependencias

```bash
git clone https://github.com/jose-14-mvj/Proyecto-De-Sistemas.git
cd Proyecto-De-Sistemas/Backend_Laravel
composer install
cp .env.example .env        # en PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

## 2. Configurar `.env`

Las variables más importantes (los valores van **siempre entre comillas** si tienen símbolos):

| Variable | Qué es |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Dónde está la base. Local: `127.0.0.1`, `3306`, `migrasense` |
| `DB_USERNAME`, `DB_PASSWORD` | Usuario de la aplicación (permisos mínimos) |
| `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` | Usuario administrador, solo para migraciones |
| `MYSQL_ATTR_SSL_CA` | Ruta al certificado `ca.pem` de Aiven (vacío en local) |
| `ML_URL`, `ML_API_KEY` | Dirección y clave del servicio de ML |
| `CORS_ALLOWED_ORIGINS` | Direcciones del frontend que pueden llamar a la API, separadas por comas |
| `FRONTEND_URL` | Dirección del frontend (se usa en los correos) |
| `CORREO_APPSSCRIPT_URL`, `CORREO_APPSSCRIPT_SECRETO`, `BREVO_API_KEY` | Envío de correos |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`, `CRON_CLAVE` | Notificaciones push y tareas programadas. Se generan con `php artisan migrasense:claves-push` |

> El archivo `.env` **nunca** se sube a GitHub. Ya está en `.gitignore`.

## 3. Crear la base de datos

Opción A, base vacía con migraciones (recomendada):

```bash
php artisan migrate --database=mysql_admin
php artisan db:seed --database=mysql_admin      # catálogos de síntomas, desencadenantes, etc.
```

Opción B, importar el script completo con vistas, procedimientos y triggers (como en Aiven):

```bash
php artisan migrasense:importar-sql database/aiven/migrasense_aiven.sql --database=mysql_admin
php artisan migrate --database=mysql_admin
```

## 4. Levantar todo

```bash
# Terminal 1: API
cd Backend_Laravel
php artisan serve                       # http://127.0.0.1:8000/api

# Terminal 2: frontend (desde la raíz del repo)
python -m http.server 5500              # http://127.0.0.1:5500
```

Por defecto el frontend habla con la API publicada en Render. Para que use tu API local, abre la consola
del navegador (F12) y ejecuta:

```js
localStorage.setItem('ms_api_url', 'http://127.0.0.1:8000/api')
```

Para volver a la nube: `localStorage.removeItem('ms_api_url')`.

## 5. Correr las pruebas

```bash
cd Backend_Laravel
php artisan test
```

Las pruebas usan una base **SQLite en memoria** que se crea y se borra en cada prueba, así que nunca tocan
la base real.

## Comandos útiles

| Comando | Para qué |
|---|---|
| `php artisan migrasense:importar-sql <archivo> --database=mysql_admin` | Importa un script SQL (con procedimientos y triggers) |
| `php artisan migrasense:claves-push` | Genera las claves VAPID y la clave del cron |
| `php artisan migrasense:enviar-recordatorios --ahora="2026-10-21 20:00"` | Simula el envío de recordatorios a una hora dada |
| `php artisan migrasense:probar-correo <correo>` | Verifica que el envío de correos esté bien configurado |
| `php artisan migrasense:ml-reentrenar` | Pide al servicio de ML que se reentrene con los datos reales |
| `php artisan migrasense:panel-ml` | Muestra la memoria y métricas del modelo |

## Problemas comunes

| Mensaje | Causa y solución |
|---|---|
| `SQLSTATE[HY000] [1045] Access denied` | Clave equivocada en `.env`. Revísala y corre `php artisan config:clear`. |
| `SQLSTATE[HY000] [1044]` | El usuario no tiene permiso sobre esa base. Revisa `DB_DATABASE`. |
| `could not find driver` | Falta activar `pdo_mysql` (o `pdo_sqlite` para las pruebas) en `php.ini`. |
| La app dice "Conectando con el servidor…" mucho rato | Render estaba dormido; la primera petición puede tardar hasta un minuto. |
| Error de CORS en la consola | Agrega la dirección del frontend a `CORS_ALLOWED_ORIGINS` y corre `php artisan config:clear`. |
