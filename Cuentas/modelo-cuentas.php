<?php
// Añadimos el fichero con la configuración de la conexión a la base de datos
require_once "db.php";

class ModeloCuentas{
    // Atributos de la clase
    // Objeto para el control de la base de datos
    private $dbo;
    // Para JWT
    private $jwt;
    private $sesionValida;
    private $id_usuario;
    // Datos de la solicitud
    private $datosSolicitud;
    // Para los datos de cuentas
    private $idCuenta;
    private $saldoAnterior;
    private $nombreAnterior;
    private $ultimaCuenta;
    private $listaCuentas;
    // Control de errores
    private $estado;

    // Métodos
    // Constructor
    public function __construct(){
        // Instanciamos el controlador de la base de datos
        $db = new DBO();
        $this->dbo = $db->conectar("cuentas_db");
        // Si hay error en la conexión cerramos el script devolviendo el error a front
        if ($this->dbo->getError() === null){
            $mensajeError = [
                "estado"  => $this->dbo->getError(),
                "mensaje" => "Fallo al conectar con cuentas_db"
            ];
            json_encode($mensajeError);
            exit();
        }
        // Inicializmos la lista vacia
        $this->listaCuentas = [];
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
    public function leerCuentas(){
        $valido = false;
        if ($this->sesionValida){
            try{
                // Preparamos la consulta para obtener los datos de usuario en caso de que la clave sea válida
                $consulta = "SELECT * FROM cuentas WHERE id_usuario_cuenta = ? AND activa = true";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute([$this->id_usuario]);
                // Obtenemos los resultados asociados a sus nombres de columna
                $datos = $solicitud->fetchAll();
                // comprobamos que hay datos
                if ($datos){
                    foreach ($datos as $fila){
                        //$cuenta = [];
                        $this->listaCuentas[] = $fila;
                    }
                    $this->estado = "Se han leido " . count($datos) . " cuentas";
                    $valido = true;
                } else {
                    $this->estado = "No hay cuentas para el usuario: " . $this->id_usuario; 
                }

            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar obtener los datos de cuentas: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    // Para crear una cuenta
    function crearCuenta(){
        $valido = false;
        if ($this->sesionValida){
            try{
                // Obtenemos los datos de la solicitud
                $nombreCuenta = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->nuevaCuenta));
                $valorSaldo = htmlspecialchars($this->datosSolicitud->saldoNuevaCuenta);
                $saldoInicial = $this->formatearEuros($valorSaldo);
                $tipoCuenta = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->tipoCuenta));
                // Damos valor a los campos restantes que no dependen del formulario
                $id_usuario = $this->id_usuario;
                $fecha_creacion = date("Y-m-d H:i:s");
                $activa = true;

                // Comprobamos que el nombre no existe ya en la base de datos
                $solicitud = $this->dbo->prepare("SELECT id_cuenta FROM cuentas WHERE nombre_cuenta = ?;");
                $solicitud->execute([$nombreCuenta]);
                $idcuenta = $solicitud->fetch();
                if (!$idcuenta){
                    // Introduzco los valores en la base de datos
                    $consulta = "INSERT INTO cuentas (id_usuario_cuenta, nombre_cuenta, tipo_cuenta, saldo_cuenta, fecha_creacion, activa) 
                                VALUES (?,?,?,?,?,?);";
                    $solicitud = $this->dbo->prepare($consulta);
                    $solicitud->execute([$id_usuario, $nombreCuenta, $tipoCuenta, $saldoInicial, $fecha_creacion, $activa]);
                    $valido = true;
                } else {
                    // Guardo el error
                    $this->estado = "El nombre de cuenta ya existe";
                }
            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar insertar los datos de cuentas: Error: " . $e->getMessage();
            }
            try{
                // Se lee el último elemento añadido
                $consulta = "SELECT * FROM cuentas WHERE id_usuario_cuenta=? ORDER BY id_cuenta DESC";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute([$this->id_usuario]);
                // Se obtienen los resultados asociados a sus nombres de columna
                $datos = $solicitud->fetch();
                //var_dump($datos);
                // Se comprueba que hay datos
                if ($datos){
                    $this->ultimaCuenta = $datos;
                }
            } catch(Exception $e){
                $this->estado = "Error al intentar leer el último elemento almacenado: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    //Para modificar cuenta
    function modificarCuenta($tipoModificacion, $saldoEntrada = null, $cuenta = null){
        if ($this->sesionValida){
            $valido = false;
            try{
                switch ($tipoModificacion){
                    case 'ajuste':{
                        $nombreCuenta = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->nuevoNombreCuenta));
                        $valorNuevoSaldo = htmlspecialchars($this->datosSolicitud->nuevoSaldoCuenta);
                        if (!isset($this->datosSolicitud->modificarCuentaPorOperacion)){
                            $valorNuevoSaldo = $this->formatearEuros($valorNuevoSaldo);
                        }
                        $saldo = ($saldoEntrada == null) 
                                    ? $valorNuevoSaldo
                                    : $saldoEntrada;
                    };
                    break;

                    case 'reversion':{
                        $nombreCuenta = $this->nombreAnterior;
                        $saldo = $this->saldoAnterior;
                    };
                    break; 
                }
                $tipoModificacion = (isset($this->datosSolicitud->nuevoNombreCuenta))? "ajuste" : "operacion" ;
                $id_cuenta = ($cuenta == null) ? $this->idCuenta : $cuenta;
                $this->leerCuenta($id_cuenta);
                $this->nombreAnterior = $this->ultimaCuenta["nombre_cuenta"];
                // Se almacenan los datos modificados de en la cuenta segun el tipo de operacion
                if ($tipoModificacion === 'ajuste' || $tipoModificacion === 'reversion'){
                    $consulta = "UPDATE cuentas SET nombre_cuenta = ?, saldo_cuenta = ? WHERE id_cuenta = ?;";
                    $solicitud = $this->dbo->prepare($consulta);
                    $solicitud->execute([$nombreCuenta, $saldo, $id_cuenta]);
                } else {
                    $consulta = "UPDATE cuentas SET saldo_cuenta = ? WHERE id_cuenta = ?;";
                    $solicitud = $this->dbo->prepare($consulta);
                    $solicitud->execute([$saldo, $id_cuenta]);              
                }
                // Traigo los datos de la cuenta modificados 
                if ($this->leerCuenta($id_cuenta)){
                    // Añadimos el saldo anterior a los datos de la cuenta
                    array_push($this->ultimaCuenta, ["saldoAnterior" => $this->saldoAnterior]);
                    $valido = true;
                } else {
                    $this->estado = "Error al realizar la modificacion";
                }
            } catch(Exception $e){
                $this->estado = "Error al intentar modificar la cuenta. Error: " . $e->getMessage();
            }    
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    //Funcion para desactivar cuenta
    function borrarCuenta(){
        if ($this->sesionValida){
            try{
                $valido = false;
                // Obtenemos los datos de la solicitud
                $id_cuenta = (!isset($this->datosSolicitud->id_cuenta)) ? $this->ultimaCuenta["id_cuenta"] : preg_replace('[^0-9]', '', htmlspecialchars($this->datosSolicitud->id_cuenta));
                // Actualizo
                $consulta = "UPDATE cuentas SET activa = false WHERE id_cuenta = ?;";
                $solicitud = $this->dbo->prepare($consulta);
                $solicitud->execute([$id_cuenta]);
                $valido = true;
            } catch(Exception $e){
                $this->estado = "Error al intentar borrar la cuenta. Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    //Agregar al historial el estado de la cuenta tras cualquier operacion
    function agregarHistorial($movimiento){
        $valido = false;
        try{
            $idMovimiento = $movimiento->idOperacion;
            $tipoMovimiento = $movimiento->tipoOperacion;
            $tipoMovimiento = ($tipoMovimiento === "t_recibida" || $tipoMovimiento === "t_enviada") ? "transferencia": $tipoMovimiento;
            $fechaCreacion = date("Y-m-d H:i:s");
            // Leo la modificación
            $consulta = "INSERT INTO historial_cuenta (id_cuenta_historial, saldo_historial, fecha_anotacion, tipo_movimiento, id_movimiento, saldo_anterior) 
                        VALUES (?,?,?,?,?,?)";
            $solicitud = $this->dbo->prepare($consulta);
            $solicitud->execute([$this->ultimaCuenta["id_cuenta"], $this->ultimaCuenta["saldo_cuenta"], $fechaCreacion,$tipoMovimiento,$idMovimiento,$this->saldoAnterior]);
            $valido = true;
        } catch(Exception $e){
            $this->estado = "Error al añadir el evento al histórico. Error: " . $e->getMessage();
        }
        return $valido;
    }
    //Obtener saldo inicial de la cuenta                   
    function saldoAnterior($id_cuenta){
        // Almacenamos el saldo anterior al cambio
        try{
            $consulta = "SELECT saldo_cuenta FROM cuentas WHERE id_cuenta = ?";
            $solicitud = $this->dbo->prepare($consulta);
            $solicitud->execute([$id_cuenta]);
            $datos = $solicitud->fetch();
            if ($datos){
                $this->saldoAnterior = $datos["saldo_cuenta"];
            }
        } catch (Exception $e){
            $this->estado = "Error al intentar leer el saldo de la cuenta a modificar. Error: " . $e->getMessage();
        }
        return $this->saldoAnterior;
    }
    //Leer datos de una cuenta
    function leerCuenta($id_cuenta){
        $valido = false;
        try{
            $consulta = "SELECT * FROM cuentas WHERE id_cuenta = ?";
            $solicitud = $this->dbo->prepare($consulta);
            $solicitud->execute([$id_cuenta]);
            $datos = $solicitud->fetch();
            if ($datos){
                $this->ultimaCuenta = $datos;
                $valido = true;
            }
        } catch(Exception $e){
            $this->estado = "Error al intentar leer el último elemento almacenado. Error: " . $e->getMessage();
        }
        return $valido;
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
    
    // Getters and Setters
    // Getters
    function getListaCuentas(){
        return $this->listaCuentas;
    }
    function getToken(){
        return $this->jwt;
    }
    function getEstado(){
        return $this->estado;
    }
    function getUltimaCuenta(){
        return $this->ultimaCuenta;
    }
    function getIdCuenta(){
        return $this->idCuenta;
    }  
    function getDatosSolicitud(){
        return $this->datosSolicitud;
    }
    function getSaldoAnterior(){
        return $this->saldoAnterior;
    }
    function getNombreAnterior(){
        return $this->nombreAnterior;
    }
    // Setters
    function setUserId($userid){
        $this->id_usuario = $userid;
    }
    function setSesionValida($sesion){
        $this->sesionValida = $sesion;
    }
    function setSaldoAnterior($saldoAnterior){
        $this->saldoAnterior = $saldoAnterior;
    }
    function setNombreAnterior($nombreAnterior){
        $this->nombreAnterior = $nombreAnterior;
    }
}
?>