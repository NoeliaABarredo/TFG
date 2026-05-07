<?php
// debemos requerir el comprobador de sesion para rechazar si no hay sesion y alguien logra acceder a la ruta directamente
require_once "modelo-operaciones.php";

class ControladorOperaciones{
    const ERRORES_BBDD = [
        "Fallo al conectar con el servidor SQL de cuentas",
	    "Fallo al conectar con la base de datos cuentas_db"
    ];
    // Atributos de la clase
    // Gestion del modelo para acceso a la base de datos
    private $modeloOperaciones;
    // Para el estado de la sesion
    private $sesionValida;
    // Para el estado de operaciones
    private $estado;
    private $estadoOperacion;

    // Métodos
    // Constructor
    function __construct(){
        // Instanciamos el modelo y lo guardamos en un atributo de la clase
        $this->modeloOperaciones = new ModeloOperaciones();
        $this->estadoOperacion ="";

        // Al instanciar el controlador compruebo el token
        $this->sesionValida = $this->validarToken();
        $this->modeloOperaciones->setSesionValida($this->sesionValida);
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
    // Tenemos que hacer un POST a usuarios.php para validar el token
    function validarToken(){
        // Preparamos los datos para la solicitud
        $url = "http://usuarios/usuarios.php/existeSesion";
        $datos = [
            "token" => $this->modeloOperaciones->getToken(),
        ];
        // Hacemos la solicitud al microservicio usuarios.php
        $respuesta = $this->solicitudPOST($url, $datos);

        //$valido = ($respuesta["mensaje"] === "Sesion valida") ? true : false;
        if ($respuesta["mensaje"] === "Sesion valida") {
            $valido = true;
            $this->modeloOperaciones->setUserId($respuesta["id_usuario"]);
        } else 
            $valido = false;
        return $valido;
    }
    // Leer operaciones
    public function leerOperaciones(){
        $listaOperaciones = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al leer operaciones"   
        ];
        
        if ($this->sesionValida){
            if ($this->modeloOperaciones->leerOperaciones()){
                $listaOperaciones = [
                    "listaOperaciones"  => $this->modeloOperaciones->getListaOperaciones(),
                    "estado"        => $this->modeloOperaciones->getEstado(),
                    "mensaje"       => "Operaciones leidas con exito"
                ];
            } else {
                $listaOperaciones["estado"] = $this->modeloOperaciones->getEstado();
            }
        }
        echo json_encode($listaOperaciones);
    }

    // Leer operaciones filtradas por tipo y/o fecha
    public function leerOperacionesFiltradas(){
        $listaOperacionesFiltradas = [
            "estado"        => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"       => "Error al leer operaciones",
            "operaciones"   => []   
        ];
        
        if ($this->sesionValida){
            if ($this->modeloOperaciones->leerOperacionesFiltradas()){
                $listaOperacionesFiltradas = [
                    "estado"        => $this->modeloOperaciones->getEstado(),
                    "mensaje"       => "Operaciones leidas con exito",
                    "operaciones"   => $this->modeloOperaciones->getListaOperacionesFiltradas()
                ];
            } else {
                $listaOperacionesFiltradas["estado"] = $this->modeloOperaciones->getEstado();
            }
        }
        echo json_encode($listaOperacionesFiltradas);
    }
    //Registrar un ingreso
    public function ingresar(){
        $retornoOperacion = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al realizar el ingreso"   
        ];
        if ($this->sesionValida){
            $monto = $this->modeloOperaciones->getDatosSolicitud()->montoIngresar;
            $monto = (float) $this->formatearEuros($monto);
            $descripcion = $this->modeloOperaciones->getDatosSolicitud()->descripcionIngreso;
            $categoria = $this->modeloOperaciones->getDatosSolicitud()->categoriaIngreso;
            $descripcion = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($descripcion));
            $categoria = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($categoria));

            if ($this->modeloOperaciones->agregarOperacion($monto, $descripcion, $categoria, "ingreso")){
                $retornoOperacion = [
                    "ultimaOperacion" => $this->modeloOperaciones->getUltimaOperacion(),
                    "estado"        => $this->modeloOperaciones->getEstado(),
                    "mensaje"       => "Ingreso realizado con éxito"
                ];
                $saldoInicial = $this->modeloOperaciones->getDatosSolicitud()->saldo_cuenta;
                $saldoInicial = (float) $this->formatearEuros($saldoInicial);
                $saldoFinal = $saldoInicial + $monto;
                $actualizarSaldoCuenta = (object) $this->modificarCuenta($saldoFinal);
                if (isset($actualizarSaldoCuenta->mensaje) && $actualizarSaldoCuenta !== null && !in_array($actualizarSaldoCuenta->mensaje,ControladorOperaciones::ERRORES_BBDD)){
                    $retornoOperacion["datosCuenta"] = $actualizarSaldoCuenta->datosCuenta;
                    $retornoOperacion["estado"] = $this->estadoOperacion;
                } else {
                    $this->modeloOperaciones->actualizarEstadoOperacion("fallida");
                    $retornoOperacion["estadoOperacion"] = $this->estadoOperacion;
                    //$retornoOperacion["estado"] = $actualizarSaldoCuenta->mensaje;
                }
            } else {
                $retornoOperacion["estado"] = $this->modeloOperaciones->getEstado();
            }
        }
        echo json_encode($retornoOperacion);
    }
    //Registrar un gasto
    public function gastar(){
        $retornoOperacion = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al realizar el gasto"   
        ];
        if ($this->sesionValida){
            $monto = $this->modeloOperaciones->getDatosSolicitud()->montoGastar;
            $monto = (float) $this->formatearEuros($monto);
            $saldoInicial = $this->modeloOperaciones->getDatosSolicitud()->saldo_cuenta;
            $saldoInicial = (float) $this->formatearEuros($saldoInicial);

            if ($saldoInicial>$monto){
                $descripcion = $this->modeloOperaciones->getDatosSolicitud()->descripcionGasto;
                $categoria = $this->modeloOperaciones->getDatosSolicitud()->categoriaGasto;
                $descripcion = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($descripcion));
                $categoria = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($categoria));
       
                if ($this->modeloOperaciones->agregarOperacion($monto, $descripcion, $categoria, "gasto")){
                    $retornoOperacion = [
                        "ultimaOperacion" => $this->modeloOperaciones->getUltimaOperacion(),
                        "estado"        => $this->modeloOperaciones->getEstado(),
                        "mensaje"       => "Gasto registrado con éxito"
                    ];
                    
                    $saldoFinal = $saldoInicial - $monto;
                    $actualizarSaldoCuenta = (object) $this->modificarCuenta($saldoFinal);
                    if ($actualizarSaldoCuenta !== null && !in_array($actualizarSaldoCuenta->mensaje,ControladorOperaciones::ERRORES_BBDD)){
                        $retornoOperacion["datosCuenta"] = $actualizarSaldoCuenta->datosCuenta;
                        $retornoOperacion["estado"] = $this->estadoOperacion;
                    } else {
                        $this->modeloOperaciones->actualizarEstadoOperacion("fallida");
                        $retornoOperacion["estadoOperacion"] = $this->estadoOperacion;
                        $retornoOperacion["estado"] = $actualizarSaldoCuenta->mensaje;
                    }
                } else {
                    $retornoOperacion["estado"] = $this->modeloOperaciones->getEstado();
                }
            }
            else
                {
                    $retornoOperacion = [
                        "ultimaOperacion" => $this->modeloOperaciones->getUltimaOperacion(),
                        "estado"          => $this->modeloOperaciones->getEstado(),
                        "mensaje"         => "Saldo insuficiente"
                    ];
                    //$retornoOperacion["estado"] = 'fallida';
            }
            
        }
        echo json_encode($retornoOperacion);
    }
    //Hacer una transferencia entre dos cuentas
    public function transferir(){
         $retornoTransfer = [
             "estado"    => "Sesión no válida. No tienes acceso a este recurso",
             "mensaje"   => "Error al realizar la transferencia"   
         ];
         $this->transferencia = [];
         if ($this->sesionValida){
            // Lanzamos la primera etapa de la transferencia. Retirar el dinero del origen
            $this->transferencia["idCuentaOrigen"] = $this->modeloOperaciones->getIdCuenta();
            $this->transferencia["idCuentaDestino"] = preg_replace('[^0-9.]', '', htmlspecialchars($this->modeloOperaciones->getDatosSolicitud()->opcionesDestinoTransferencia));
            $this->transferencia["saldoAnteriorOrigen"] = (float) $this->formatearEuros($this->modeloOperaciones->getDatosSolicitud()->saldo_cuenta);
            $this->transferencia["monto"] = (float) $this->formatearEuros($this->modeloOperaciones->getDatosSolicitud()->montoTransferir);
            $this->transferencia["descripcion"] = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->modeloOperaciones->getDatosSolicitud()->descripcionTransferir));
            $this->transferencia["nombreCuenta"] = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->modeloOperaciones->getDatosSolicitud()->nombre_cuenta));
            $this->transferencia["categoria"] = "t_enviada";
            $this->transferencia["estado"] = "pendiente";
            
            if ($this->transferencia["saldoAnteriorOrigen"] > $this->transferencia["monto"]){
                // Se lanza la primera etapa y se aprovecha el ajuste de la primera cuenta para leer el saldo de la segunda
                if ($this->etapaTransferencia($this->transferencia["categoria"], $this->transferencia)){
                    // Se actualiza el estado de la operacion
                    if (!$this->modeloOperaciones->actualizarEstadoOperacion("completada")){}
                        $this->transferencia["estado"] = $this->modeloOperaciones->getEstado();
                    
                    $retornoTransfer["EstadoEtapas"] = "Dinero descontado de la cuenta origen con éxito";
                    $retornoTransfer["mensaje"] = "Pendiente";
                    $retornoTransfer["estado"] = $this->transferencia["estado"];
                    //$this->transferencia["mensaje"] = "Transferencia realizada con exito";
                    $this->transferencia["categoria"] = "t_recibida";

                    // Todo ha ido bien en la primera etapa descontando el saldo de la cuenta origen y ejecutamos la segunda
                    if ($this->etapaTransferencia($this->transferencia["categoria"], $this->transferencia)){
                        // Todo ha ido bien en la segunda etapa añadiendo el saldo de la cuenta destino, validamos la transferencia
                        if (!$this->modeloOperaciones->actualizarEstadoOperacion("completada"))
                            $this->transferencia["estado"] = $this->modeloOperaciones->getEstado();

                        //$this->transferencia["mensaje"] = "Transferencia realizada con exito";

                        // Se anota la transferencia en la tabla de transferencias
                        
                        $retornoTransfer["estado"] = $this->transferencia["estado"];
                        $retornoTransfer["mensaje"] = "Transferencia realizada con éxito";
                        // Marco el estado para guardar en la tabla
                        $this->transferencia["estado"] = "completada";
                        $this->modeloOperaciones->agregarTransferencia($this->transferencia);
                        $valido = true;
                    } else {
                        // Estados tras ejecutar la segunda etapa
                        $retornoTransfer["EstadoEtapas"] = $this->transferencia["estado"];
                        $retornoTransfer["mensaje"] = "Error al añadir el dinero en la cuenta de destino";
                        
                        // La segunda etapa ha fracasado y debemos restablecer las dos cuentas
                        $restablecerCuenta = $this->modificarCuenta($this->transferencia["saldoAnteriorOrigen"]);
                        // Se gestionan los estados
                        $retornoTransfer["restablecimientoCuentaOrigen"] = $this->estadoOperacion;  
                        $retornoTransfer["estado"] = "Transferencia fallida";

                        // Cambio el tipo de categoria para restablecer la segunda cuenta
                        $this->transferencia["categoria"] = "t_enviada";
                        $restablecerCuenta = $this->modificarCuenta($this->transferencia["saldoAnteriorDestino"]);
                        // Se gestionan los estados
                        $retornoTransfer["restablecimientoCuentaDestino"] = $this->estadoOperacion;  
                        $retornoTransfer["estado"] = "Transferencia fallida";

                        //$this->transferencia["estado"] = "fallida";
                        // Actualizamos el estado de la operacion
                        if (!$this->modeloOperaciones->actualizarEstadoOperacion("fallida"))
                            $this->transferencia["estado"] = $this->modeloOperaciones->getEstado();
                        // Falta desacer en las dos cuentas
                    }
                } else {
                    // Estados tras ejecutar la primera etapa
                    $retornoTransfer["EstadoEtapas"] = $this->transferencia["estado"]; 
                    $retornoTransfer["mensaje"] = "Error al descontar el dinero en la cuenta de origen";

                    // La primera etapa ha fracasado y debemos restablecer la cuenta de origen. No se cursará la segunda etapa.
                    $restablecerCuenta = $this->modificarCuenta($this->transferencia["saldoAnteriorOrigen"]);
                    // Se gestionan los estados
                    $retornoTransfer["restablecimientoCuentaOrigen"] = $this->estadoOperacion;  
                    $retornoTransfer["estado"] = "Transferencia fallida";

                    //$this->transferencia["estado"] = "fallida";
                    // Actualizamos el estado de la operacion
                    if (!$this->modeloOperaciones->actualizarEstadoOperacion("fallida"))
                        $this->transferencia["estado"] = $this->modeloOperaciones->getEstado();
                }
            } else {
                $retornoTransfer['mensaje'] = "Saldo insuficiente";
            }              
        }
        echo json_encode($retornoTransfer);
    }
    private function etapaTransferencia($etapa, $cuentaEtapa = null){
        $valido = false;
        $retornoOperacion = [
            "estado"        => "Error al agregar la operacion",
            "mensaje"       => "Error al agregar la operacion"
        ];
        // Marcamos el tipo de etapa para la selcción de mensajes y datos
        // Si es true etamos en primera etapa monto sale de la cuenta origen.
        // En segunda etapa el monto entra en la cuenta destino
        $selectorEtapa = ($etapa === "t_enviada") ? true : false;

        $concepto = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->modeloOperaciones->getDatosSolicitud()->descripcionTransferir));
        // Se elige el origen del saldo en funcion de la etapa
        $saldoAnterior = ($selectorEtapa) ? $cuentaEtapa["saldoAnteriorOrigen"] : $cuentaEtapa["saldoAnteriorDestino"];
        $monto = $cuentaEtapa["monto"];
        // En primera etapa resto del origen. En segunda sumo al destino
        $saldoFinal = ($selectorEtapa) ? $saldoAnterior - $monto : $saldoAnterior + $monto;

        // Ejecutamos la modificacion tras ajustar los datos de la operación
        if ($this->modeloOperaciones->agregarOperacion($monto, $cuentaEtapa["descripcion"], $cuentaEtapa["categoria"], $etapa)){
            $retornoOperacion = [
                "ultimaOperacion" => $this->modeloOperaciones->getUltimaOperacion(),
                "estado"        => $this->modeloOperaciones->getEstado(),
                "mensaje"       => "Saldo descontado con exito en cuenta de origen"
            ];

            // Se envía el ajuste a cuentas
            $actualizarSaldoCuenta = (object) $this->modificarCuenta($saldoFinal);
            if ($actualizarSaldoCuenta !== null && !in_array($actualizarSaldoCuenta->mensaje,ControladorOperaciones::ERRORES_BBDD)){
                if ($selectorEtapa){
                     // Se almacena el id de la operacion
                    $this->transferencia["idOperacionTransferir"] = $this->modeloOperaciones->getUltimoId();
                    // No hay que formatear el saldo pues viene de la bbdd de cuentas
                    $this->transferencia["saldoAnteriorDestino"] = (float) $actualizarSaldoCuenta->saldoCuentaDestino;
                } else {
                    // Se almacena el id de la operacion
                    $this->transferencia["idOperacionRecibir"] = $this->modeloOperaciones->getUltimoId();
                } 
                $valido = true;
            } else {
                        $this->modeloOperaciones->actualizarEstadoOperacion("fallida");
                        $retornoOperacion["estadoOperacion"] = $this->estadoOperacion;
                        $retornoOperacion["estado"] = $actualizarSaldoCuenta->mensaje;
            }
        } else {
            $retornoOperacion["estado"] = $this->modeloOperaciones->getEstado();
        }
        // Capturo los estados que se hayan podido generar
        $this->transferencia["estado"] = $retornoOperacion;
        return $valido;
    }

    // Añadir operación
    public function agregarOperacion(){
        $retornoOperaciones = [
            "estado"    => "Sesión no válida. No tienes acceso a este recurso",
            "mensaje"   => "Error al añadir Gasto"   
        ];
        
        if ($this->sesionValida){
            $monto=$this->modeloOperaciones->getDatosSolicitud()->monto;
            $descripcion = $this->modeloOperaciones->getDatosSolicitud()->descripcion;
            $categoria = $this->modeloOperaciones->getDatosSolicitud()->categoria;
            $tipo = $this->modeloOperaciones->getDatosSolicitud()->tipo;
            $tipo = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($tipo));

            if ($this->modeloOperaciones->agregarOperacion($monto, $descripcion, $categoria, $tipo)){
                $retornoOperaciones = [
                    "idOperacion"   => $this->modeloOperaciones->getUltimoId(),
                    "tipoOperacion" => $this->modeloOperaciones->getTipoOperacion(),
                    "estado"        => $this->modeloOperaciones->getEstado(),
                    "mensaje"       => "Gasto añadida con exito"
                ];
            } else {
                $retornoOperaciones["estado"] = $this->modeloOperaciones->getEstado();
            }
        }
        echo json_encode($retornoOperaciones);
    }
    
    function modificarCuenta($saldoFinal){
        // Si existe cuenta de destino en la solicitud se coge como id principal
        $idCuenta = (isset($this->modeloOperaciones->getDatosSolicitud()->opcionesDestinoTransferencia) && (isset($this->transferencia)) && ($this->transferencia["categoria"] === "t_recibida")) 
            ? $this->transferencia["idCuentaDestino"] 
            : $this->modeloOperaciones->getDatosSolicitud()->id_cuenta;
        $idCuentaDestino = (isset($this->transferencia["idCuentaDestino"])) ? $this->transferencia["idCuentaDestino"] : null;
        // enviamos la modificacion al microservicio de cuentas
        $url = "http://cuentas/cuentas.php/modificar-cuenta";
        $datos = [
            "token"                         => $this->modeloOperaciones->getToken(),
            "modificarCuentaPorOperacion"   => true,
            "id_cuenta"                     => $idCuenta,
            "id_cuenta_destino"             => $idCuentaDestino,
            "nuevoSaldoCuenta"              => $saldoFinal,
            "idOperacion"                   => $this->modeloOperaciones->getUltimaOperacion()['id_operacion'],
            "tipoOperacion"                 => $this->modeloOperaciones->getUltimaOperacion()['tipo_operacion'],
            "tipoCategoria"                 => $this->modeloOperaciones->getUltimaOperacion()['tipo_categoria'],
            "mensaje"                       => "Solicitud desde operaciones"
        ];
        $respuesta = $this->solicitudPOST($url, $datos);
        return $respuesta;
    }

    //Formatear euros
    function formatearEuros($valor){
        // Quitar separador de miles (.)
        $valorSaldo = str_replace('.', '', $valor);
        // Convertir coma decimal a punto
        $valorSaldo = str_replace(',', '.', $valorSaldo);
        // Eliminar cualquier otro carácter raro
        $valorSaldo = preg_replace('/[^0-9.]/', '', $valorSaldo);
        return $valorSaldo;
    }

    # Getters and Setters
    public function getSesionValida(){
        return $this->sesionValida;
    }
}
?>