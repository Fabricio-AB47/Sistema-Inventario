<?php
// Punto de entrada público para registrar movimientos cuando el servidor tiene como raíz la carpeta /public.
// Redirigimos la solicitud al script real ubicado fuera del documento público.
require_once __DIR__ . '/../../api/movimientos.php';
