<?php
// Dependencias
require_once "controlador-operaciones.php";

// Leemos los datos recibidos por JSON
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Separamos las partes de la url para seleccionar la subruta
$uri = explode( '/operaciones.php/', $uri );
$indexpoint = 1; // posicion de la url donde está el punto.

// Instanciamos el controlador para acceder a sus métodos
$controladorOperaciones = new ControladorOperaciones();
// Comprobamos si ya existe una sesión
if ($controladorOperaciones->getSesionValida()){
    if ($method === "POST"){
        if ($uri[$indexpoint] === "leer-operaciones"){
            $controladorOperaciones->leerOperaciones();
        }
        if ($uri[$indexpoint] === "leer-operaciones-filtradas"){
            $controladorOperaciones->leerOperacionesFiltradas();
        }
        if ($uri[$indexpoint] === "ingresar"){
            $controladorOperaciones->ingresar();
        }
        if ($uri[$indexpoint] === "gastar"){
            $controladorOperaciones->gastar();
        }
        if ($uri[$indexpoint] === "transferir"){
            $controladorOperaciones->transferir();
        }
        if ($uri[$indexpoint] === "agregar-operacion"){
            $controladorOperaciones->agregarOperacion();
        }
    }
} else {
    // devolver 403 por falta de privilegios
    http_response_code(403);
    $mensajeRetorno = [
        "mensaje" => "No tienes permiso para acceder a este recurso"
    ];
    echo json_encode($mensajeRetorno);
}
?>