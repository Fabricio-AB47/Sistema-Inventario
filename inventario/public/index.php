<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// El módulo de actas ya no se usa; redirigimos al panel de equipos.
header('Location: admin-equipos.php');
exit;
