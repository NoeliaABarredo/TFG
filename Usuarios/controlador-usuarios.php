<?php
// debemos requerir el comprobador de sesion para rechazar si no hay sesion y alguien logra acceder a la ruta directamente
require_once "modelo-usuarios.php";

class ControladorUsuarios{
    // Atributos de la clase
    // Gestion del modelo para acceso a la base de datos
    private $modeloUsuario;
    // Métodos
    // Constructor
    function __construct(){
        // Instanciamos el modelo y lo guardamos en un atributo de la clase
        $this->modeloUsuario = new ModeloUsuario();
    }
    // Comprobar si existe sesion
    public function existeSesion(){
        $infoRetorno = [];
        if ($this->modeloUsuario->comprobarToken()){
            $infoRetorno = [
                "id_usuario" => $this->modeloUsuario->getUserId(),
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"    => "Sesion valida"
            ];
        } else {
            $infoRetorno = [
                "estado"    => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Sesion invalida"
            ];
        }
        echo json_encode($infoRetorno);
    }
    public function comprobarToken(){
        return $this->modeloUsuario->comprobarToken();
    }
    // Funcion para login
    function login(){
        $infoUsuario = [];
        if ($this->modeloUsuario->login()){
            // Si el login tiene éxito obtenemos info de usuario y la retornamos a la presentación
            $infoUsuario = $this->modeloUsuario->getDatosUsuario();
        } else {
            // Si el login no tiene éxito devolvemos un mensaje de error y obtenemos el estado reportado por login
            $infoUsuario = [
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Error de login"
            ];
        }
        echo json_encode($infoUsuario);
    }

    // Función para registrar un usuario
    function registrar(){
        $infoRetorno = [];
        if ($this->modeloUsuario->registrar()){
            $infoRetorno = [
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Usuario registrado con exito"
            ];
        } else {
            $infoRetorno = [
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Error al registrar usuario"
            ];
        }
        echo json_encode($infoRetorno);
    }

    // Función para leer un usuario
    function leerUsuario(){
        $infoUsuario = [];
        if ($this->modeloUsuario->leerUsuario()){
            // Si lee los datos de usuario, se retornan
            $infoUsuario = [
                "datosUsuario"    => $this->modeloUsuario->getDatosUsuario(),
                "estado"   => $this->modeloUsuario->getEstado(),
                "mensaje"  => "Datos del usuario leidos"
            ];
        } else {
            // Si no se leen los datos del usuario con éxito
            $infoUsuario = [
                "estado"   => $this->modeloUsuario->getEstado(),
                "mensaje"  => "Error al leer los datos del usuario"
            ];
        }
        echo json_encode($infoUsuario);
    }

    function modificarUsuario(){
        $infoRetorno = [];
        if ($this->modeloUsuario->modificarUsuario()){
              $infoRetorno = [
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Usuario modificado con exito"
            ];
        } else {
            $infoRetorno = [
                "estado"     => $this->modeloUsuario->getEstado(),
                "mensaje"   => "Error al modificar usuario"
            ];
        }
        echo json_encode($infoRetorno);
    }    
}
?>