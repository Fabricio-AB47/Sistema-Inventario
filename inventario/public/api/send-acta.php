<?php
// Punto de entrada público para envío de actas cuando el root del sitio es /public.
// Incluimos el script real que vive fuera del directorio público.
require_once __DIR__ . '/../../api/send-acta.php';
