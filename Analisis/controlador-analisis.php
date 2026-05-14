<?php

class ControladorAnalisis{
    const ERRORES_BBDD = [
        "Fallo al conectar con el servidor SQL de cuentas",
	    "Fallo al conectar con la base de datos cuentas_db",
        "Fallo al conectar con el servidor SQL de operaciones",
	    "Fallo al conectar con la base de datos operaciones_db"
    ];
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
        $url = "http://usuarios/usuarios.php/existeSesion";
        $datos = [
            "token" => $this->jwt,
        ];
        // Hacemos la solicitud al microservicio usuarios.php
        $respuesta = $this->solicitudPOST($url, $datos);

        //$valido = ($respuesta["mensaje"] === "Sesion valida") ? true : false;
        if (isset($respuesta["mensaje"]) && $respuesta["mensaje"] === "Sesion valida") {
            $valido = true;
            $this->id_usuario = $respuesta["id_usuario"];
        } else {
            $valido = false;
            if (!isset($respuesta["mensaje"])){
                $this->estado = "El servicio de usuarios no está disponible";
            }
        }
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
        } elseif (isset($respuesta['mensaje']) && in_array($respuesta['mensaje'],ControladorAnalisis::ERRORES_BBDD)){
            return [
                "ok" => false,
                "mensaje" => $actualizarSaldoCuenta->mensaje
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
        $retornoAhorro = [
            "ahorro"  => $ahorro,
            "mensaje" => "Ahorro calculado con éxito"
        ];
        echo json_encode($retornoAhorro);
    }
    public function supervivenciaFinanciera(){
        // Se obtienen los gastos de los últimos 30 días
        $url = "http://operaciones/operaciones.php/leer-operaciones-filtradas";
        //$filtrosSolicitud = $this->datosSolicitud->filtros;        
        $filtros = [
                "fecha_inicio" => date('Y-m-d H:i:s', strtotime('-30 days')),
                "tipo"         => "gasto"
            ];
        
        $datos = [
            "token"   => $this->jwt,
            "filtros" => $filtros,
            "cuentas" => $this->datosSolicitud->cuentas
        ];
        $respuestaOP = $this->solicitudPOST($url, $datos);
       
         if (!$respuestaOP || empty($respuestaOP['operaciones'])){
            return [
                "ok" => false,
                "mensaje" => "No hay operaciones"
            ];
        } elseif (isset($respuestaOP['mensaje']) && in_array($respuestaOP['mensaje'],ControladorAnalisis::ERRORES_BBDD)){
            return [
                "ok" => false,
                "mensaje" => $actualizarSaldoCuenta->mensaje
            ];
        }
        
        // Calcular gasto
        $gastos = 0;

        foreach ($respuestaOP['operaciones'] as $op){
            $gastos += $op['monto'];
        }

        // Se obtienen las cuentas para calcular el patrimonio
        $url = "http://cuentas/cuentas.php/leer-cuentas";

        $datos = [
            "token"   => $this->jwt
        ];

        $respuestaCuentas = $this->solicitudPOST($url, $datos);

        if (!$respuestaCuentas || empty($respuestaCuentas['listaCuentas'])){
            return [
                "ok" => false,
                "mensaje" => "No hay cuentas"
            ];
        } elseif (isset($respuestaCuentas['mensaje']) && in_array($respuestaCuentas['mensaje'],ControladorAnalisis::ERRORES_BBDD)){
            return [
                "ok" => false,
                "mensaje" => $actualizarSaldoCuenta->mensaje
            ];
        }
        $patrimonio = 0;
        // Se suman los saldos de todas las cuentas
        foreach($respuestaCuentas["listaCuentas"] as $cuenta){
            $patrimonio += $cuenta["saldo_cuenta"];
        }

        $mesesSupervivencia = $patrimonio / $gastos;

        $retornoInfo = [
            "mesesSupervivencia" => $mesesSupervivencia,
            "patrimonio"         => $patrimonio,
            "gastos"             => $gastos,
            "mensaje"            => "Ahorro calculado con éxito"
        ];
        echo json_encode($retornoInfo);
    }
    // # Getters and Setters
     public function getSesionValida(){
         return $this->sesionValida;
     }
     public function getEstadoComunicacion(){
        return $this->estadoComunicacion;
     }
     public function getEstado(){
        return $this->estado;
     }
}
?>