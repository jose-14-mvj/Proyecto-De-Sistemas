# MigraSense — Backend (API en Laravel)

Esta carpeta es la API de MigraSense. Recibe lo que la persona registra desde la app, lo guarda en MySQL
(Aiven), calcula estadísticas y reportes, pide la predicción al servicio de machine learning y envía los
recordatorios.

- Laravel 9 · PHP 8.2 · Sanctum · MySQL 8.4
- Publicada en `https://migrasence-api.onrender.com/api`
- Documentación de endpoints: [docs/api.md](../docs/api.md)

## Organización

```
app/
├── Console/Commands/   Comandos propios (importar SQL, recordatorios, ML, correo, claves push)
├── Http/
│   ├── Controllers/    Un controlador por recurso (Auth, Registro, Perfil, Reporte, Prediccion…)
│   ├── Requests/       Validación de cada petición
│   └── Middleware/     Cabeceras de seguridad, respuestas siempre en JSON
├── Models/             Modelos de las tablas y vistas
├── Services/           Lógica del negocio (RegistroService, PrediccionService, CorreoService…)
└── Support/            Web Push (VAPID) y PDF del resumen semanal
database/
├── aiven/              Script completo de la base (tablas, vistas, procedimientos, triggers)
├── migrations/         Cambios de estructura (se pueden correr varias veces sin romper nada)
└── seeders/            Catálogos de síntomas, desencadenantes y condiciones
routes/api.php          Todas las rutas de la API
tests/                  104 pruebas automáticas (PHPUnit, SQLite en memoria)
docker/, Dockerfile     Imagen que usa Render
```

## Ponerlo en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --database=mysql_admin
php artisan db:seed --database=mysql_admin
php artisan serve
```

La guía completa, con cada variable de entorno y los errores más comunes, está en
[docs/instalacion.md](../docs/instalacion.md).

## Pruebas

```bash
php artisan test
```

Las pruebas nunca tocan la base real: todas las conexiones se redirigen a SQLite en memoria.
