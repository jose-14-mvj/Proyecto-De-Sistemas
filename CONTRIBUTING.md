# Cómo trabajamos en MigraSense

Este archivo resume los acuerdos del equipo para que el repositorio no se vuelva un caos. Si eres nuevo en
el proyecto, léelo antes de tu primer commit.

## 1. Ramas

| Rama | Para qué sirve | Quién la toca |
|---|---|---|
| `main` | Lo que está publicado (GitHub Pages y Render se actualizan desde aquí). Siempre debe funcionar. | Nadie directo: solo entra código por pull request |
| `develop` | Integración: aquí se juntan las tareas antes de pasar a `main`. | Por pull request |
| `feature/<tema>` | Una funcionalidad nueva. Ej.: `feature/migrana-en-curso` | Quien toma el issue |
| `fix/<tema>` | Corrección de un error. Ej.: `fix/duracion-obligatoria` | Quien toma el issue |
| `docs/<tema>` | Solo documentación. Ej.: `docs/manual-usuario` | Quien toma el issue |

Flujo normal:

```bash
git checkout develop
git pull
git checkout -b feature/nombre-corto        # una rama por issue
# … trabajas y haces commits …
git push -u origin feature/nombre-corto
# en GitHub: abrir Pull Request hacia develop y escribir "Closes #<número del issue>"
```

Cuando `develop` está estable y probado, se abre un PR de `develop` a `main`, se publica y se crea la
versión (ver punto 5).

## 2. Commits

Escribimos los mensajes en español, en presente y diciendo **qué** cambia. Usamos un prefijo para saber de
qué tipo es cada cambio:

| Prefijo | Cuándo |
|---|---|
| `feat:` | funcionalidad nueva |
| `fix:` | corrección de un error |
| `docs:` | documentación |
| `style:` | diseño o formato, sin cambiar la lógica |
| `refactor:` | se reorganiza código sin cambiar lo que hace |
| `test:` | pruebas automáticas |
| `chore:` | configuración, dependencias, despliegue |

Ejemplos:

```
feat: agrega el botón "La migraña terminó" en Inicio
fix: la duración ya no es obligatoria cuando la migraña sigue en curso
docs: manual de usuario con capturas
```

Reglas simples: un commit = un cambio con sentido; nada de "cambios", "asdf" o "subo todo".
**Nunca** se suben `.env`, `vendor/`, `node_modules/`, el certificado `ca.pem` ni contraseñas.

## 3. Issues

Todo trabajo empieza como un issue (pestaña *Issues* → *New issue*). Hay dos plantillas:

- **Tarea**: algo que hay que construir o mejorar.
- **Error**: algo que no funciona como debería (con pasos para reproducirlo).

Etiquetas que usamos:

| Etiqueta | Significado |
|---|---|
| `frontend` · `backend` · `base-de-datos` · `ml` · `despliegue` · `documentacion` | Parte del sistema |
| `funcionalidad` · `error` · `seguridad` · `mejora` | Tipo de trabajo |
| `prioridad: alta` · `prioridad: media` · `prioridad: baja` | Urgencia |

Cada issue se asigna a una persona y a un **hito** (milestone), que corresponde a una versión.

## 4. Tablero de tareas

En la pestaña *Projects* está el tablero **MigraSense – Tablero de tareas** con cuatro columnas:
**Por hacer → En progreso → En revisión → Hecho**. Al crear un issue entra a "Por hacer"; cuando alguien
abre la rama pasa a "En progreso"; con el PR abierto va a "En revisión", y al fusionarse el PR el issue se
cierra solo y queda en "Hecho".

## 5. Pull requests

- Un PR por issue, hacia `develop` (o hacia `main` solo para correcciones urgentes de producción).
- Título con el mismo formato que los commits.
- En la descripción: qué cambia, cómo se probó y `Closes #N`.
- Antes de pedir revisión: `php artisan test` debe pasar (si tocaste el backend) y hay que probar la
  pantalla en celular y en PC (si tocaste el frontend).
- Otro integrante revisa y aprueba antes de fusionar.

## 6. Versiones

Usamos versionado semántico `MAYOR.MENOR.PARCHE`:

- **MAYOR** (`2.0.0`): cambios que rompen lo anterior (por ejemplo, cambiar la API de forma incompatible).
- **MENOR** (`1.5.0`): funcionalidades nuevas que no rompen nada.
- **PARCHE** (`1.5.1`): solo correcciones.

Al publicar una versión:

1. Se agrega su sección en [CHANGELOG.md](CHANGELOG.md).
2. Se crea la etiqueta: `git tag -a v1.6.0 -m "v1.6.0 - descripción"` y `git push origin v1.6.0`.
3. En GitHub → *Releases* → *Draft a new release* se elige la etiqueta y se pega el resumen del changelog.

## 7. Si cambias la base de datos

Los cambios de estructura van **siempre** en una migración nueva de Laravel
(`Backend_Laravel/database/migrations`), escrita para que se pueda correr dos veces sin romper nada, y se
aplican con el usuario administrador **antes** de desplegar el código que las usa:

```bash
php artisan migrate --database=mysql_admin --force
```
