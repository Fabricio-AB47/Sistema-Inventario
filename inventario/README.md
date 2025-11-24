# Inventario de Actas y Recepción

Panel ligero para capturar y seguir actas/recepciones. PHP guarda los registros en `data/inventory.json`, mientras que Gulp compila SCSS y JS para la UI.

## Requisitos
- Node.js + npm
- PHP (se asume XAMPP/Apache apuntando a `htdocs/inventario`)

## Instalación
```bash
npm install
npm run build  # genera assets en public/assets
```

Abre `http://localhost/inventario/public` desde tu servidor web. El API está en `http://localhost/inventario/api/inventory.php`.

## Scripts disponibles
- `npm run build`: compila SCSS y JS en modo producción.
- `npm run dev`: limpia y compila, luego deja los watchers corriendo.
- `npm run watch`: solo observadores (sin limpiar).
- `npm run serve`: watchers + BrowserSync (proxy a `http://localhost/inventario` por defecto; ajusta en `gulpfile.js` si usas otra ruta/puerto).

## Estructura breve
- `public/index.php`: interfaz para crear, filtrar y cambiar estados.
- `api/inventory.php`: CRUD básico sobre `data/inventory.json` (POST crear, GET listar, PUT/PATCH actualizar estado/detalles, DELETE eliminar).
- `api/auth.php`: registro/login con sesiones PHP (almacenados en `data/users.json`).
- Login ahora consulta tabla `USUARIO` (correo + psw en BD) y arma permisos: solo `id_tipo_usuario` 1 (ADMINISTRADOR) y 5 (TI) pueden crear usuarios/equipos y modificar inventario.
- `api/send-acta.php`: recibe acta, firmas y PDF (base64) y lo envía por correo como adjunto.
- `api/users.php`: alta/listado de usuarios (solo ADMINISTRADOR/TI).
- `api/catalogs.php`: lista tipos de equipo y estados desde la BD.
- `api/equipos.php`: alta/listado de equipos (solo ADMINISTRADOR/TI para alta).
- `src/scss/main.scss` y `src/js/app.js`: código fuente que se compila a `public/assets`.
- `src/js/acta-sign.js`, `src/js/acta-pdf.js`: firmas digitales (canvas) y generación/envío de PDF desde `public/acta.php`.
- `gulpfile.js`: tareas de estilos, scripts, watch/serve.
- `database/schema.sql`: esquema SQL sugerido (tipos de usuario, equipos, estados y movimientos).
- `.env`: variables de conexión a BD y SMTP (ajusta con tus valores reales).

## Notas
- El archivo `data/inventory.json` debe ser escribible por PHP.
- Los estados posibles: `pendiente`, `progreso`, `cerrado`.
- Tipos permitidos: `acta`, `recepcion`.
- Para usar login, entra en `public/login.php` con un usuario existente en la tabla `USUARIO` (correo + psw); luego navega a `public/index.php` o `public/acta.php`.
- Para que el envío de correos funcione, configura `mail()` de PHP (SMTP) en tu servidor. El PDF se genera en el navegador y se envía base64 al backend para adjuntarlo.
- Ajusta `.env` con tus credenciales de BD (SQL Server por defecto). La columna `psw` de la tabla `USUARIO` se compara en texto plano según la petición (correo + psw).
