<?php
// Dependencias
require_once "controlador-usuarios.php";

// Leemos los datos recibidos por JSON
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Separamos las partes de la url para seleccionar la subruta
$uri = explode( '/usuarios.php/', $uri );
$indexpoint = 1; // posicion de la url donde está el punto.
//var_dump($uri);

// Instanciamos el controlador para acceder a sus métodos
$controladorUsuarios = new ControladorUsuarios();

if ($method === "POST"){
    // Comprobar si existe sesion
    if ($uri[$indexpoint] === "existeSesion"){
        $controladorUsuarios->existeSesion();
    }
}

//Comprobar token
if ($controladorUsuarios->comprobarToken()){
    if ($method === "POST"){
        // Entrada para leer y modificar usuario
        if ($uri[$indexpoint] === "leerUsuario"){
            $controladorUsuarios->leerUsuario();
        }
        if ($uri[$indexpoint] === "modificarUsuario"){
            $controladorUsuarios->modificarUsuario();
        }
    }
} else {
    if ($method === "POST"){
        // Entrada para login o registro
        if ($uri[$indexpoint] === "login"){
            $controladorUsuarios->login();
        }
        if ($uri[$indexpoint] === "registro"){
            $controladorUsuarios->registrar();
        }
    }
} 
?>