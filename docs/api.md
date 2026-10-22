# API REST

- **Dirección publicada:** `https://migrasence-api.onrender.com/api`
- **Formato:** JSON en UTF-8. Se recomienda enviar `Accept: application/json`.
- **Autenticación:** token de Laravel Sanctum en la cabecera `Authorization: Bearer <token>`. El token se
  obtiene al registrarse o iniciar sesión y vence a las 12 horas.
- **Fechas:** se aceptan `"2026-10-21T18:30"` (hora de Bolivia) o ISO con zona (`"2026-10-21T22:30:00Z"`).
  El servidor responde en hora de Bolivia sin zona (`"2026-10-21T18:30:00"`).

## Respuestas de error

| Código | Cuándo | Cuerpo |
|---|---|---|
| 401 | Falta el token o venció | `{"message": "Unauthenticated."}` |
| 404 | El recurso no existe **o es de otra persona** | `{"error": "Registro no encontrado"}` |
| 422 | Datos inválidos | `{"message": "...", "errors": {"campo": ["mensaje"]}}` |
| 429 | Demasiados intentos | `{"message": "Too Many Attempts."}` |
| 503 | El servicio de ML no respondió (solo `/prediccion`) | `{"error": "..."}` |

## Cuenta y sesión

| Método | Ruta | Token | Descripción |
|---|---|---|---|
| POST | `/registro` | No | Crea la cuenta. Cuerpo: `name, email, password, password_confirmation`. Devuelve `token`, `usuario` |
| POST | `/login` | No | Inicia sesión: `email, password`. Límite: 5 intentos por minuto |
| POST | `/logout` | Sí | Cierra la sesión actual (revoca ese token) |
| GET | `/sesion` | Sí | Datos del usuario y de su perfil |
| PUT | `/cuenta` | Sí | Cambia nombre, correo o foto |
| PUT | `/cuenta/clave` | Sí | Cambia la contraseña (pide la actual) |
| DELETE | `/cuenta` | Sí | Elimina la cuenta y todos sus datos (pide la contraseña) |
| POST | `/clave/olvide` | No | Envía un código de recuperación al correo |
| POST | `/clave/restablecer` | No | Cambia la contraseña con el código recibido |

## Perfil (cuestionario inicial)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/perfil` | Respuestas del cuestionario |
| PUT | `/perfil` | Guarda el cuestionario: `genero, ocupacion, tiempoMigrana, frecuencia, tieneOrden, ordenAparicion, momentoDia, duracion, intensidad, conocesProdromica, sueno, estres, cafeina, porcionesCafeina, condiciones[]` |

## Registros

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/catalogos` | Síntomas prodrómicos y desencadenantes activos |
| GET | `/registros?tipo=todos\|prodromico\|episodio&dias=30&limite=500` | Historial, del más reciente al más antiguo |
| POST | `/registros/prodromico` | Nuevo síntoma prodrómico |
| POST | `/registros/episodio` | Nueva migraña |
| GET | `/registros/en-curso` | Migraña marcada como "Aún en curso" (o `null`) |
| POST | `/registros/episodio/{id}/terminar` | "La migraña terminó". Cuerpo opcional: `fin` |
| GET | `/registros/{tipo}/{id}` | Un registro |
| PUT | `/registros/{tipo}/{id}` | Editar (solo cambia lo que se envía) |
| DELETE | `/registros/{tipo}/{id}` | Eliminar |

### Ejemplo: registrar una migraña que está pasando ahora

```http
POST /api/registros/episodio
Authorization: Bearer 12|abc...
Content-Type: application/json

{
  "intensidad": 7,
  "inicio": "2026-10-21T18:30",
  "en_curso": true,
  "aura": "si",
  "sintomas": ["luz", "bostezos_palidez"],
  "disparadores": ["estres", "sueno"],
  "medicacion": true,
  "medicamento": "Ibuprofeno 400 mg",
  "notas": "Empezó después de clases"
}
```

Campos del episodio:

| Campo | Obligatorio | Valores |
|---|---|---|
| `intensidad` | Sí | 1 a 10 |
| `inicio` | No (por defecto, ahora) | fecha y hora, no futura |
| `fin` | Si no hay `en_curso` ni `duracion` | fecha y hora posterior al inicio, máximo 30 días después |
| `en_curso` | No | `true` si la migraña sigue. Solo puede haber una a la vez |
| `duracion` | Solo si no hay `fin` ni `en_curso` | `menos_4h`, `4_24h`, `1_3d`, `mas_3d` |
| `aura` | No | `si`, `no`, `no_se` |
| `sintomas` | No | códigos de `/catalogos` (se puede enviar `ninguno`) |
| `disparadores` | No | códigos de `/catalogos` |
| `medicacion`, `medicamento`, `notas` | No | booleano, texto (80), texto (500) |

Respuesta `201`:

```json
{
  "ok": true,
  "registro": {
    "id": 42, "type": "episodio", "label": "Episodio de migraña",
    "intensidad": 7, "inicio": "2026-10-21T18:30:00", "date": "2026-10-21T18:30:00",
    "sintomas": ["Sensibilidad a la luz", "Bostezos o palidez"],
    "disparadores": ["Estrés", "Falta de sueño"],
    "duracion": "menos_4h", "enCurso": true, "fin": null, "duracionMinutos": null,
    "aura": "si", "medicacion": true, "medicamento": "Ibuprofeno 400 mg",
    "notas": "Empezó después de clases"
  }
}
```

## Predicción, estadísticas y reportes

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/prediccion` | Probabilidad de migraña en las próximas 48 h, nivel (`bajo`, `moderado`, `alto`, `muy_alto`), factores que más pesaron y confianza. Límite: 10 por minuto |
| GET | `/estadisticas?semanas=12&top=5` | Resumen de 30 días, episodios por semana, desencadenantes y síntomas más frecuentes, patrones |
| POST | `/reportes` | Genera un reporte: `dias` (30, 90, 365 o 0 = todo) y `formato` (`pdf` o `txt`) |
| GET | `/reportes` | Reportes generados |
| GET | `/reportes/{id}` | Un reporte con sus registros |
| DELETE | `/reportes/{id}` | Elimina un reporte |

## Recordatorios y notificaciones

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/recordatorios` | `{daily:{on,time}, weekly:{on,day,time}, alerta:{on}}` |
| PUT | `/recordatorios` | Cambia uno o varios recordatorios |
| GET | `/notificaciones?estado=&limite=` | Notificaciones de la persona |
| POST | `/notificaciones` | Crea una notificación |
| PUT | `/notificaciones/{id}` | Marca como leída o descartada |
| GET | `/push/clave` | Clave pública VAPID para suscribirse a avisos push |
| POST | `/push/suscripcion` | Registra este dispositivo para recibir avisos |
| DELETE | `/push/suscripcion` | Deja de recibir avisos en este dispositivo |
| POST | `/push/prueba` | Envía un aviso de prueba |
| POST | `/tareas/recordatorios` | Uso interno del cron: cabecera `X-Cron-Key` |
