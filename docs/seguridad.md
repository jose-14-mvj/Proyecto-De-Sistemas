# Seguridad

MigraSense guarda datos de salud, así que la seguridad fue parte del diseño desde la primera versión del
backend y no un agregado al final.

## Cuentas y contraseñas

- Contraseñas guardadas con **Argon2id** (memoria 64 MB, 4 iteraciones); si cambian los parámetros, el hash
  se actualiza solo en el siguiente inicio de sesión.
- Contraseña mínima de 10 caracteres con mayúscula, minúscula y número.
- El mensaje de error de inicio de sesión es el mismo si el correo no existe o si la clave está mal (no se
  puede averiguar qué correos están registrados).
- Tokens de Sanctum que vencen a las 12 horas; cerrar sesión revoca el token de ese dispositivo.
- Recuperación de contraseña con código por correo: máximo 5 por día y 1 por minuto.
- La persona puede eliminar su cuenta y con ella todos sus datos (borrado en cascada).

## Acceso a los datos

- Cada consulta se filtra por el usuario del token: un registro de otra persona responde **404**, como si no
  existiera (protección contra IDOR).
- Todas las entradas pasan por una clase de validación (FormRequest) antes de llegar a la lógica.
- Los procedimientos almacenados y triggers vuelven a validar en la base (fechas futuras, catálogos).

## Límites de intentos

| Acción | Límite |
|---|---|
| Inicio de sesión | 5 por minuto por correo e IP, 20 por minuto por IP |
| Crear cuenta | 10 por hora por IP |
| Acciones sensibles (cambiar clave, borrar cuenta) | 5 por minuto |
| Predicción | 10 por minuto |
| Resto de la API | 60 por minuto |

## Transporte y servidor

- Todo por HTTPS. La conexión de la API a la base usa **TLS** con el certificado de Aiven, y el usuario de la
  base está creado con `REQUIRE SSL`.
- Cabeceras de seguridad en todas las respuestas: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy: no-referrer`, `Content-Security-Policy`, `Cache-Control: no-store`, y HSTS cuando se
  sirve por HTTPS.
- CORS solo acepta el dominio del frontend.
- En producción `APP_DEBUG=false`, los errores no muestran detalles y los logs no incluyen argumentos de
  funciones (para que nunca aparezcan claves).
- El servicio de ML y la tarea programada exigen una clave propia en una cabecera y comparan con
  `hash_equals`.
- Las suscripciones push solo aceptan servidores de push conocidos (evita que alguien use la API para
  llamar a direcciones arbitrarias).

## Secretos

`.env`, el certificado `ca.pem` y las claves VAPID **nunca** se suben al repositorio. En Render se cargan
como variables de entorno y *Secret Files*.

## Pendientes

Están como issues en GitHub: cifrar notas y medicamentos en la base, actualizar a Laravel 12 (Laravel 9 ya
no recibe parches) y configurar respaldos programados.
