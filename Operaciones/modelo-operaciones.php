<?php
// Añadimos el fichero con la configuración de la conexión a la base de datos
require_once "db.php";

class ModeloOperaciones{
    // Atributos de la clase
    // Objeto para el control de la base de datos
    private $dbo;
    private $ultimoId;
    // Para JWT
    private $jwt;
    private $sesionValida;
    private $id_usuario;
    // Datos de la solicitud
    private $idCuenta;
    private $datosSolicitud;
    private $listaCuentas;
    private $listaOperaciones;
    private $ultimaOperacion;
    private $tipoOperacion;
    private $listaOperacionesFiltradas;
    // Control de errores
    private $estado;

    // Métodos
    // Constructor
    public function __construct(){
        $this->estado = null;
        // Instanciamos el controlador de la base de datos
        $db = new DBO();
        // Si hay error en la conexión cerramos el script devolviendo el error a front
        if ($db->getError() !== null){
            $mensajeError = [
                "estado"  => $db->getError(),
                "mensaje" => "Fallo al conectar con el servidor SQL de operaciones"
            ];
            echo json_encode($mensajeError);
            exit();
        }
        // Si no hay error en conexión con el servidor, conectamos con la base de datos
        $this->dbo = $db->conectar("operaciones_db");
        // Estamos en el servidor pero no encontramos la base de datos
        if (!$this->dbo){
            $mensajeError = [
                "estado"  => $db->getError(),
                "mensaje" => "Fallo al conectar con la base de datos operaciones_db"
            ];
            echo json_encode($mensajeError);
            exit();
        }
        // Se inicializan las listas vacias
        $this->listaOperaciones = [];
        $this->listaCuentas = [];
        //$this->tipoOperacion;
        // Inicialmente la sesión no es valida hasta que se haga expresamente. ZeroTrust
        $this->sesionValida = false;
        // Al crear el modelo leemos los datos que vienen en la solicitud.
        // Los filtramos y validamos para evitar ataques de inyección SQL
        $this->datosSolicitud = json_decode( file_get_contents('php://input') );
        // Almacenamos el id de cuenta si se trae en la consulta
        $this->idCuenta = (isset($this->datosSolicitud->id_cuenta)) ? preg_replace('[^0-9]', '', htmlspecialchars($this->datosSolicitud->id_cuenta)) : null;
        // Si la solicitud trae un token lo almacenamos para hacer las verificaciones oportunas
        $this->jwt = (isset($this->datosSolicitud->token)) ? $this->datosSolicitud->token : null;
    }
    // Para leer cuentas
    public function leerOperaciones(){
        $valido = false;
        if ($this->sesionValida){
            try{
                // Aqui tenemos que preparar las consultas si hay filtros
                //
                // Preparamos la consulta para obtener los datos de usuario en caso de que la clave sea válida
                //$consulta = "SELECT * FROM operaciones WHERE id_cuenta_operacion IN (?) LIMIT 20;";
                // Preparamos la lista de cuentas para insertar en la consulta
                $listaCuentas = implode(',', array_map('intval', $this->datosSolicitud->cuentas));
                // Consulta para obtener la tabla con nombres y no con ids
                $consulta = "SELECT * FROM operaciones AS op
                             INNER JOIN categorias AS ca
                             ON ca.id_categoria = op.id_categoria_operacion 
                             WHERE op.id_cuenta_operacion IN ($listaCuentas)
                             ORDER BY op.fecha_operacion DESC  
                             LIMIT 25;";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute();
                $datos = $solicitud->fetchAll();
                // comprobamos que hay datos
                if ($datos){
                    foreach ($datos as $fila){
                        $this->listaOperaciones[] = $fila;
                    }
                    $this->estado = "Se han leido " . count($datos) . " operaciones";
                    $valido = true;
                } else {
                    $this->estado = "No hay operaciones para las cuentas solicitadas "; 
                }

            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar obtener las operaciones de cuentas: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }

    function leerOperacionesFiltradas(){
        $valido = false;
        if ($this->sesionValida){
            try {            
                $filtros = (array) $this->datosSolicitud->filtros;
                $listaCuentas = $this->datosSolicitud->cuentas;
             
                //Si no hay cuentas no devuelve nada
                if (empty($listaCuentas)) {
                    $this->listaOperacionesFiltradas = [];   
                    return $valido;    
                }

                //$consulta = "SELECT * FROM operaciones WHERE 1=1";
                $consulta = "SELECT * FROM operaciones AS op
                             INNER JOIN categorias AS ca
                             ON ca.id_categoria = op.id_categoria_operacion 
                             WHERE 1=1";
                $params = [];

                $idsCuentas = implode(',', array_fill(0, count($listaCuentas), '?'));
                //$consulta .= " AND id_cuenta_operacion IN ($idsCuentas)";
                $consulta .= " AND op.id_cuenta_operacion IN ($idsCuentas)";
                $params = array_merge($params, $listaCuentas);

                if (!empty($filtros['cuenta'])){
                    //$consulta .= " AND id_cuenta_operacion = ?";
                    $consulta .= " AND op.id_cuenta_operacion = ?";
                    $params[] = $filtros['cuenta'];
                }

                if (!empty($filtros['tipo'])){
                    //$consulta .= " AND tipo_operacion = ?";
                    $consulta .= " AND op.tipo_operacion = ?";
                    $params[] = $filtros['tipo'];
                }

                if (!empty($filtros['fecha_inicio'])){
                    //$consulta .= " AND fecha_operacion >= ?";
                    $consulta .= " AND op.fecha_operacion >= ?";
                    $params[] = $filtros['fecha_inicio'];
                }

                if (!empty($filtros['fecha_fin'])){
                    //$consulta .= " AND fecha_operacion <= ?";
                    $consulta .= " AND op.fecha_operacion <= ?";
                    $params[] = $filtros['fecha_fin'];
                }
                //$consulta .= " ORDER BY fecha_operacion DESC";
                $consulta .= " ORDER BY op.fecha_operacion DESC LIMIT 30";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute($params);
                $datos = $solicitud->fetchAll();

                $this->listaOperacionesFiltradas = $datos;
                $valido = true;
            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar la operacion: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }

    function agregarOperacion($montoOperacion=null, $descripcion=null, $categoriaTexto=null, $tipo=null){
        $valido = false;
        if ($this->sesionValida){
            // Se obtienen los datos 
            try{
                // Se obtienen los datos de la solicitud 
                $id_cuenta =  isset($this->datosSolicitud->datosCuenta->id_cuenta)
                    ? preg_replace('[^0-9.]', '', htmlspecialchars($this->datosSolicitud->datosCuenta->id_cuenta)) 
                    : preg_replace('[^0-9.]', '', htmlspecialchars($this->datosSolicitud->id_cuenta)) ;
                if ($categoriaTexto === "t_recibida")
                    $id_cuenta = preg_replace('[^0-9.]', '', htmlspecialchars($this->datosSolicitud->opcionesDestinoTransferencia));

                $this->tipoOperacion = $tipo;
                $monto = htmlspecialchars($montoOperacion);
                //$monto = ($tipo === 'ajuste')? $valor : $this->formatearEuros($valor);
                $fecha_creacion = date("Y-m-d H:i:s");
                
                // Obtenemos el id de categoría
                $consulta = "SELECT id_categoria FROM categorias WHERE nombre_categoria = ? AND tipo_categoria = ?;";
                $solicitud = $this->dbo->prepare($consulta);
                $solicitud->execute([$categoriaTexto, $tipo]);
                $idCategoria = $solicitud->fetch();
                if ($idCategoria){
                    $idCategoria = $idCategoria["id_categoria"];
                }
                else{
                    $consultaCategoria = "INSERT INTO categorias (nombre_categoria, tipo_categoria) VALUES (?,?);";
                    $solicitudCategoria = $this->dbo->prepare($consultaCategoria);
                    $solicitudCategoria->execute([$categoriaTexto, $tipo]);
                    $idCategoria = $this->dbo->lastInsertId();
                }

                // Se adapta el tipo al campo de la base de datos 
                $tipoOperacion = ($tipo === "t_enviada" || $tipo === "t_recibida") ? "transferencia" : $tipo;
                // Se introducen los valores en la base de datos
                $consultaAgregarOperacion = "INSERT INTO operaciones (id_cuenta_operacion, tipo_operacion, monto, descripcion_operacion, id_categoria_operacion, fecha_operacion, estado_operacion) 
                            VALUES (?,?,?,?,?,?,?);";
                $solicitudAgregarOperacion = $this->dbo->prepare($consultaAgregarOperacion);
                $solicitudAgregarOperacion->execute([$id_cuenta, $tipoOperacion, $monto, $descripcion, $idCategoria,$fecha_creacion, "completada"]);
                $valido = true;
                $this->ultimoId = $this->dbo->lastInsertId();
                $this->ultimaOperacion = [
                    "id_operacion"            =>$this->ultimoId,
                    "id_cuenta_operacion"     =>$id_cuenta, 
                    "tipo_operacion"          =>$tipo, 
                    "monto"                   =>$monto, 
                    "descripcion_operacion"   =>$descripcion, 
                    "id_categoria_operacion"  =>$idCategoria,
                    "tipo_categoria"          =>$categoriaTexto, 
                    "fecha_operacion"         =>$fecha_creacion,
                    "estado_operacion"        =>"completada"
                ];

            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar la operacion: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    function actualizarEstadoOperacion($estado){
        $valido = false;
        try {
            $consultaActualizarOperacion = "UPDTATE operaciones SET estado = ? WHERE id_operacion = ?;";
            $solicitudActualizarOperacion = $this->dbo->prepare($consultaActualizarOperacion);
            $solicitudActualizarOperacion->execute([$estado, $this->ultimoId]);
            $valido = true;
        } catch (Exception $e){
            $this->estado = "Error al intentar actualizar el estado de la operacion: Error: " . $e->getMessage();
        }
        return $valido;
    }

    function agregarTransferencia($datosTransfer){
        // Se introducen los valores en la base de datos
        $fecha_creacion = date("Y-m-d H:i:s");

        $consultaAgregarTransfer = "INSERT INTO transferencias (id_cuenta_origen, id_cuenta_destino, id_operacion_origen, id_operacion_destino, monto_transferencia, concepto, fecha_transferencia, estado_transferencia) 
                    VALUES (?,?,?,?,?,?,?,?);";
        $solicitudAgregarTransfer = $this->dbo->prepare($consultaAgregarTransfer);
        $solicitudAgregarTransfer->execute([$datosTransfer["idCuentaOrigen"], $datosTransfer["idCuentaDestino"], $datosTransfer["idOperacionTransferir"], $datosTransfer["idOperacionRecibir"], $datosTransfer["monto"], $datosTransfer["descripcion"], $fecha_creacion, $datosTransfer["estado"]]);

        // Se lee la transferencia agregada ??
    }

    // Getters and Setters
    // Getters
    function getListaCuentas(){
        return $this->listaCuentas;
    }
    function getListaOperaciones(){
        return $this->listaOperaciones;
    }
    function getUltimaOperacion(){
        return $this->ultimaOperacion;
    }
    function getToken(){
        return $this->jwt;
    }
    function getEstado(){
        return $this->estado;
    }
    function getTipoOperacion(){
        return $this->tipoOperacion;
    }
    function getDatosSolicitud(){
        return $this->datosSolicitud;
    }
    function getUltimoId(){
        return $this->ultimoId;
    }
    function getIdCuenta(){
        return $this->idCuenta;
    }
    function getListaOperacionesFiltradas(){
        return $this->listaOperacionesFiltradas;
    }
    // Setters
    function setUserId($userid){
        $this->id_usuario = $userid;
    }
    function setSesionValida($sesion){
        $this->sesionValida = $sesion;
    }
}
?>