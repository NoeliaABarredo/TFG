<?php
// Dependencias
require_once "controlador-analisis.php";

// Leemos los datos recibidos por JSON
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Separamos las partes de la url para seleccionar la subruta
$uri = explode( '/analisis.php/', $uri );
$indexpoint = 1; // posicion de la url donde está el punto.

// Instanciamos el controlador para acceder a sus métodos
$controladorAnalisis = new ControladorAnalisis();

// Comprobamos si ya existe una sesión
if ($controladorAnalisis->getSesionValida()){
    if ($method === "POST"){
        // Entrada
        if ($uri[$indexpoint] === "capacidad-ahorro"){
            $controladorAnalisis->capacidadAhorro(); 
        }
        if ($uri[$indexpoint] === "supervivencia-financiera"){
            $controladorAnalisis->supervivenciaFinanciera(); 
        }
    }
} else {
    // devolver 403 por falta de privilegios
    http_response_code(403);
    $mensajeRetorno = [
        "estadoComunicacion"  => $controladorAnalisis->getEstadoComunicacion(),
        "estado"  => $controladorAnalisis->getEstado(),
        "mensaje" => "No tienes permiso para acceder a este recurso"
    ];
    echo json_encode($mensajeRetorno);
}
?>