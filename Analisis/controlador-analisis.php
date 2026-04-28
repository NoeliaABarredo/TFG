<?php

class ControladorAnalisis{
    // Atributos de la clase
    // Para el estado de la sesion
    private $sesionValida;
    private $id_usuario;
    // Para el estado de operaciones
    private $datosSolicitud;
    private $jwt;
    private $estado;
    private $estadoComunicacion;


    // Métodos
    // Constructor
    function __construct(){

        // Se obtiene los datos de la solicitud
        $this->datosSolicitud = json_decode( file_get_contents('php://input') );
        // Si la solicitud trae un token lo almacenamos para hacer las verificaciones oportunas
        $this->jwt = (isset($this->datosSolicitud->token)) ? $this->datosSolicitud->token : null;
        // Al instanciar el controlador compruebo el token
        $this->sesionValida = $this->validarToken();
    }
    // Función para las intercomunicaciones con otros microservicios.
    function solicitudPOST($ruta, $datos){
        // Codificamos en json para el envío
        $datosJSON = json_encode($datos);
        //Iniciamos la solicitud con CURL
        $solicitud = curl_init($ruta);
        //Confiuramos las opciones de la solicitud
        curl_setopt($solicitud, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($solicitud, CURLOPT_POST, true);
        curl_setopt($solicitud, CURLOPT_POSTFIELDS, $datosJSON);
        curl_setopt($solicitud, CURLOPT_HTTPHEADER, [
                                                        'Content-Type: application/json',
                                                        'Content-Length: ' . strlen($datosJSON)
                                                    ]);
        curl_setopt($solicitud, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($solicitud, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($solicitud, CURLOPT_FOLLOWLOCATION, true); // Seguir redirecciones
        // Para evitar el bloqueo del script por la conexión
        curl_setopt($solicitud, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($solicitud, CURLOPT_TIMEOUT, 20);
        // Para depurar
        curl_setopt($solicitud, CURLOPT_VERBOSE, true);
        // Ejecutamos las olicitud
        $respuesta = curl_exec($solicitud);
        //var_dump($respuesta);
        // Para la gestion de error en la comunicación
        if (curl_errno($solicitud)){
            $this->estado = "Error en la solicitud cURL: " . curl_error($solicitud);
            $resultado = $this->estado;
        } else {
            // Obtenemos el resultado decodificado en un array asociativo
            $resultado = json_decode($respuesta, true);
        }

        // Cerramos la conexión
        curl_close($solicitud);
        // Si el json_decode da null almacenamos el resultado en crudo
        if ($resultado === null)
            $this->estadoComunicacion = $respuesta;
        //var_dump($resultado);
        // Devolvemos la respuesta
        return $resultado;
    }
    // Tenemos que hacer un POST a usuarios.php para validar el token
    function validarToken(){
        // Preparamos los datos para la solicitud
        $url = "http://ususarios/usuarios.php/existeSesion";
        $datos = [
            "token" => $this->jwt,
        ];
        // Hacemos la solicitud al microservicio usuarios.php
        $respuesta = $this->solicitudPOST($url, $datos);
        if (!isset($respuesta["mensaje"])){
            var_dump($this->estadoComunicacion);
        }

        //$valido = ($respuesta["mensaje"] === "Sesion valida") ? true : false;
        if ($respuesta["mensaje"] === "Sesion valida") {
            $valido = true;
            $this->id_usuario = $respuesta["id_usuario"];
        } else 
            $valido = false;
        return $valido;
    }

// Leer operaciones filtradas
    public function capacidadAhorro(){
        $url = "http://operaciones/operaciones.php/leer-operaciones-filtradas";
        //$filtrosSolicitud = $this->datosSolicitud->filtros;        
        $filtros = [
                "fecha_inicio" => date('Y-m-d H:i:s', strtotime('-30 days'))
            ];
        
        $datos = [
            "token"   => $this->jwt,
            "filtros" => $filtros,
            "cuentas" => $this->datosSolicitud->cuentas
        ];
        $respuesta = $this->solicitudPOST($url, $datos);
        //var_dump($respuesta);
         if (!$respuesta || empty($respuesta['operaciones'])){
            return [
                "ok" => false,
                "mensaje" => "No hay operaciones"
            ];
        }

        //Calcular ahorro a partir de operaciones obtenidas
        $ingresos = 0;
        $gastos = 0;

        foreach ($respuesta['operaciones'] as $op){
            if ($op['tipo_operacion'] === 'ingreso'){
                $ingresos += $op['monto'];
            } else if ($op['tipo_operacion'] === 'gasto'){
                $gastos += $op['monto'];
            }
        }
        $ahorro = $ingresos - $gastos;
        //var_dump($ahorro);
        echo json_encode($ahorro);
    }

    // # Getters and Setters
     public function getSesionValida(){
         return $this->sesionValida;
     }
}
?>