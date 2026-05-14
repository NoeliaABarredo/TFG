<?php
// Dependencias
require_once "controlador-cuentas.php";

// Leemos los datos recibidos por JSON
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Separamos las partes de la url para seleccionar la subruta
$uri = explode( '/cuentas.php/', $uri );
$indexpoint = 1; // posicion de la url donde está el punto.

// Instanciamos el controlador para acceder a sus métodos
$controladorCuentas = new ControladorCuentas();

// Se comprueba si ya existe una sesión
if ($controladorCuentas->getSesionValida()){
    if ($method === "POST"){
        // Entrada
        if ($uri[$indexpoint] === "leer-cuentas"){
            $controladorCuentas->leerCuentas();
        }
        if ($uri[$indexpoint] === "crear-cuenta"){
            $controladorCuentas->crearCuenta();
        }
        if ($uri[$indexpoint] === "borrar-cuenta"){
            $controladorCuentas->borrarCuenta();
        }
        if ($uri[$indexpoint] === "modificar-cuenta"){
            $controladorCuentas->modificarCuenta();
        }
        if ($uri[$indexpoint] === "agregar-historico-cuenta"){
            $controladorCuentas->agregarHitoricoCuenta();
        }
    }
} else {
    // devolver 403 por falta de privilegios
    http_response_code(403);
    $mensajeRetorno = [
        "estado"  => $controladorCuentas->getEstado(),
        "mensaje" => "No tienes permiso para acceder a este recurso"
    ];
    echo json_encode($mensajeRetorno);
}
?>