<?php
// debemos requerir el comprobador de sesion para rechazar si no hay sesion y alguien logra acceder a la ruta directamente
require_once "modelo-cuentas.php";

class ControladorCuentas{
    // Atributos de la clase
    // Gestion del modelo para acceso a la base de datos
    private $modeloCuentas;
    // Para el estado de la sesion
    private $sesionValida;
    // Para el estado de operaciones
    private $estado;
    private $estadoOperacion;
    private $transferencia;

    // Métodos
    // Constructor
    function __construct(){
        $this->estado = null;
        // Instanciamos el modelo y lo guardamos en un atributo de la clase
        $this->modeloCuentas = new ModeloCuentas();
        // Comprobamos que no hay error al crear la instancia ModeloCuentas
        if ($this->modeloCuentas->getEstado() !== null)
            $this->estado = $this->modeloCuentas->getEstado();
        else {
            // Al instanciar el controlador compruebo el token
            $this->sesionValida = $this->validarToken();
            $this->modeloCuentas->setSesionValida($this->sesionValida);
        }
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
        //echo "Respuesta de operaciones <br>";
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
            $this->estadoOperacion = $respuesta;
        //var_dump($resultado);
        // Devolvemos la respuesta
        return $resultado;
    }
    // Hay que hacer un POST a usuarios.php para validar el token
    function validarToken(){
        // Preparamos los datos para la solicitud
        $url = "http://usuarios/usuarios.php/existeSesion";
        $datos = [
            "token" => $this->modeloCuentas->getToken(),
        ];
        // Hacemos la solicitud al microservicio usuarios.php
        $respuesta = $this->solicitudPOST($url, $datos);

        //$valido = ($respuesta["mensaje"] === "Sesion valida") ? true : false;
        if (!isset($respuesta["mensaje"]))
            var_dump($this->estadoOperacion);
        if ($respuesta["mensaje"] === "Sesion valida") {
            $valido = true;
            $this->modeloCuentas->setUserId($respuesta["id_usuario"]);
        } else 
            $valido = false;
        return $valido;
    }

    // Leer cuentas
    public function leerCuentas(){
        $listaCuentas = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al leer cuentas"   
        ];
        
        if ($this->sesionValida){
            if ($this->modeloCuentas->leerCuentas()){
                $listaCuentas = [
                    "listaCuentas"  => $this->modeloCuentas->getListaCuentas(),
                    "estado"        => $this->modeloCuentas->getEstado(),
                    "mensaje"       => "Cuentas leidas con exito"
                ];
            } else {
                $listaCuentas["estado"] = $this->modeloCuentas->getEstado();
            }
        }
        echo json_encode($listaCuentas);
    }
    //Crear nueva cuenta
    public function crearCuenta(){
        $retornoCuenta = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al crear cuenta"   
        ];
        
        if ($this->sesionValida){
            if ($this->modeloCuentas->crearCuenta()){
                $retornoCuenta = [
                    "datosCuenta"   => $this->modeloCuentas->getUltimaCuenta(),
                    "estado"        => $this->modeloCuentas->getEstado(),
                    "mensaje"       => "Cuenta creada con exito"
                ];
                $movimientoCrear = (object) [
                    "idOperacion" => 0,
                    "tipoOperacion" => 'activacion'
                ];
                $this->modeloCuentas->setSaldoAnterior(0);

                $indice = 0;
                $exito = false;
                while (!$exito && $indice < 3){
                    $exito = $this->modeloCuentas->agregarHistorial($movimientoCrear);
                    $indice++;
                }
                if (!$exito){
                    $this->modeloCuentas->borrarCuenta();
                    $retornoCuenta["mensaje"] = "Error al crear cuenta";
                }
            } else {
                $retornoCuenta["estado"] = $this->modeloCuentas->getEstado();
            }
        }
        echo json_encode($retornoCuenta);
    }
    //Modificar datos de cuenta
    public function modificarCuenta(){
        $retornoCuenta = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al modificar cuenta"   
        ];
        
        if ($this->sesionValida){
            $this->modeloCuentas->saldoAnterior($this->modeloCuentas->getIdCuenta());

            //Puede ser modificacion tras operacion o modificacion interna
            if ($this->modeloCuentas->modificarCuenta("ajuste")){
                $retornoCuenta = [
                    "datosCuenta"   => $this->modeloCuentas->getUltimaCuenta(),
                    "estado"        => $this->modeloCuentas->getEstado(),
                    "mensaje"       => "Cuenta modificada con exito"
                ];
                //Si se modifica tras operación
                $modificarPorOperacion = isset($this->modeloCuentas->getDatosSolicitud()->modificarCuentaPorOperacion);
                if ($modificarPorOperacion) {
                    $operacion = $this->modeloCuentas->getDatosSolicitud();
                }
                else {
                    // Enviamos la modificación al servicio de operación y recogemos la info
                    $operacion = (object) $this->enviarOperacion(
                        "Modificación de cuenta",
                        "ajuste",
                        $retornoCuenta["datosCuenta"],
                        "ajuste",
                    );
                }                
                // Añadimos el cambio al historial de la cuenta
                if ($operacion !== null){
                    $indice = 0;
                    $exito = false;
                    while (!$exito && $indice < 3){
                        $exito = $this->modeloCuentas->agregarHistorial($operacion);
                        $indice++;
                    }
                    if (!$exito){ //Si no se puede añadir al historial
                        // Revertir la modificacion
                        $this->modeloCuentas->modificarCuenta("reversion");
                        $retornoCuenta["estado"] = $this->modeloCuentas->getEstado();
                        $retornoCuenta["mensajeHistorial"] = "Error al agregar la modificación al historial de cuenta";
                        $retornoCuenta["mensaje"] = "Error al crear cuenta";
                    }
                } else {//operacion null
                    // Revertir la modificacion
                    $this->modeloCuentas->modificarCuenta("reversion");
                    $retornoCuenta["estadoOperacion"] = $this->estadoOperacion;
                }
                // Leemos la cuenta destino solo si existe el parámetro y el tipo de operación es t_enviada
                // Solo en t_enviada necesitamos el saldo de la cuenta destino y aprovechamos la modificación de
                // la cuenta origen para obtener este dato
                $idCuentaDestino = (isset($this->modeloCuentas->getDatosSolicitud()->id_cuenta_destino)) ? $this->modeloCuentas->getDatosSolicitud()->id_cuenta_destino : null;
                
                if ($idCuentaDestino !== null && ($operacion->tipoOperacion == "t_enviada")){
                    $this->modeloCuentas->leerCuenta($idCuentaDestino);
                    $retornoCuenta["saldoCuentaDestino"] = $this->modeloCuentas->getUltimaCuenta()["saldo_cuenta"];
                }
            } else {//No se ha modificado
                $retornoCuenta["estado"] = $this->modeloCuentas->getEstado();
            }
        }
        echo json_encode($retornoCuenta);
    }
    //Desactivas cuenta
    public function borrarCuenta(){
        $retornoCuenta = [
            "datosCuenta"   => $this->modeloCuentas->leerCuenta($this->modeloCuentas->getIdCuenta()),
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al borrar cuenta"   
        ];
        
        if ($this->sesionValida){
            if ($this->modeloCuentas->borrarCuenta()){
                $retornoCuenta = [
                    "estado"        => $this->modeloCuentas->getEstado(),
                    "mensaje"       => "Cuenta borrada con exito"
                ];
                $movimientoBorrar = (object) [
                    "idOperacion" => 0,
                    "tipoOperacion" => 'desactivacion'
                ];
                $this->modeloCuentas->saldoAnterior($this->modeloCuentas->getIdCuenta());
                $this->modeloCuentas->agregarHistorial($movimientoBorrar);

            } else {
                $retornoCuenta["estado"] = $this->modeloCuentas->getEstado();
            }
        }
        echo json_encode($retornoCuenta);
    }
    
    // Comunicaciones con otros servicios
    public function enviarOperacion($descripcion, $categoria, $cuenta, $tipo, $monto = null, $estado = "completada"){
        $montoEnviar = ($monto == null) ? $cuenta["saldo_cuenta"] : $monto;
        // enviamos la modificacion al microservicio de operaciones
        $url = "http://operaciones/operaciones.php/agregar-operacion";
        $datos = [
            "token"         => $this->modeloCuentas->getToken(),
            "descripcion"   => $descripcion,
            "categoria"     => $categoria,
            "datosCuenta"   => $cuenta,
            "tipo"          => $tipo,
            "monto"         => $montoEnviar,
            "estado"        => $estado
        ];
        $respuesta = $this->solicitudPOST($url, $datos);
        return $respuesta;
    }

    // # Getters and Setters
     public function getSesionValida(){
         return $this->sesionValida;
     }

    function getEstado(){
        return $this->estado;
    }
}
?>