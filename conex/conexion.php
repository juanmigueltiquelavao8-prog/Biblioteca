<?php
// Configuración de los parámetros de la base de datos
$servidor = "localhost";
$usuario  = "root";
$password = "";
$bd       = "biblioteca";

// Conexión mediante el objeto nativo mysqli (POO básico)
$conexion = new mysqli($servidor, $usuario, $password, $bd);

// Verificación de conexión
if ($conexion->connect_error) {
    die("Error al conectar con la base de datos: " . $conexion->connect_error);
}

// Configurar codificación de caracteres
$conexion->set_charset("utf8mb4");
?>