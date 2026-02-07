<?php
require_once 'config/database.php';
iniciarSesion();

if (isset($_SESSION['usuario_id'])) {
    registrarLog('logout', "Cierre de sesión", $_SESSION['usuario_id']);
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit();
?>