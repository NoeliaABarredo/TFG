<?php
// Añadimos el fichero con la configuración de la conexión a la base de datos
require_once "db.php";
# Para JWT
require_once __DIR__ . '/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ModeloUsuario{
    // Atributos de la clase
    // Objeto para el control de la base de datos
    private $dbo;
    // Para JWT
    private $jwt;
    private $claveJwt;
    private $sesionValida;
    // Datos de la solicitud
    private $datosSolicitud;
    // Para los datos de usuario
    private $userid;
    private $nombre;
    private $apellidos;
    private $email;
    private $telefono;
    private $rol;
    private $fechaCreacion;
    private $fechaLogin;
    private $activo;
    // Control de errores
    private $estado;

    // Métodos
    // Constructor
    public function __construct(){
        // De momento hardcodeado. En despliegue sobre k3s se gestionará con los SECRETS y variables de entorno "getenv("JWT_KEY")"
        //$this->claveJwt = "DsvAQzXEfHvYkl9S92pzgJcCgPmr697081kJslNh85o=";
        $this->claveJwt = getenv('JWT_TOKEN');
        // Instanciamos el controlador de la base de datos
        $db = new DBO();
        $this->dbo = $db->conectar("usuarios_db");
        // Inicialmente la sesión no es valida hasta que se haga expresamente. ZeroTrust
        $this->sesionValida = false;
        // Al crear el modelo leemos los datos que vienen en la solicitud.
        // Los filtramos y validamos para evitar ataques de inyección SQL
        $this->datosSolicitud = json_decode( file_get_contents('php://input') );
        // Si la solicitud trae un token lo almacenamos para hacer las verificaciones oportunas
        $this->jwt = (isset($this->datosSolicitud->token)) ? $this->datosSolicitud->token : null;
        //var_dump($this->jwt);
    }

    // Función para comprobar si hay sesion
    public function comprobarToken(){
        // logica para comprobar el token
        $valido = false;
        try {
            // Extraigo la info del token
            if ($this->jwt !== null){
                $decoded = JWT::decode($this->jwt, new Key($this->claveJwt, 'HS256'));
                $this->userid = $decoded->data->id;
                $this->estado = "Token valido";
                $this->sesionValida = true;
                $valido = true;
            } else {
                $this->estado = "No hay token";
            }
        } catch (Exception $e){
            $this->estado = "Token inválido o caducado";
        } 
        return $valido;
    }

    // Para abrir sesión
    public function login(){
        $valido = false;
        $usuario = filter_var($this->datosSolicitud->idlogin, FILTER_VALIDATE_EMAIL); // ver si es necesario htmlspecialchars
        // La clave no se filtra pues solo se usará para verificar con la hasheada en la base de datos y no tendrá más interacción
        $clave = $this->datosSolicitud->clave;
        try{
            if ($usuario !== "" && !is_null($usuario)){
                // Preparamos la consulta para obtener los datos de usuario en caso de que la clave sea válida
                $consulta = "SELECT * FROM usuarios WHERE email=?";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute([$usuario]);
                // Obtenemos los resultados asociados a sus nombres de columna
                //$resultado = $solicitud->get_result();
                // filtro para evitar errores con resultados null en fetch_assoc
                //$datos = ($resultado === null ) ? null : $resultado->fetch_assoc();
                $datos = $solicitud->fetch();
                //if ($datos !=="" && !is_null($datos)){
                if ($datos){
                    if (password_verify($clave, $datos["password_hash"])){
                        // Exito en el login. Obetenemos los datos y los almacenamos en el modelo
                        $this->userid        = $datos["id_usuario"];
                        $this->nombre        = $datos["nombre"];
                        $this->apellidos     = $datos["apellidos"];
                        $this->email         = $datos["email"];
                        $this->telefono      = $datos["telefono"];
                        $this->rol           = $datos["rol"];
                        $this->fechaCreacion = $datos["fecha_creacion"];
                        $this->fechaLogin    = $datos["fecha_login"];
                        // Generamos el payload para el JWT y lo generamos
                        $payload = [
                            "iss"  => "appfinanzas.local",
                            "aud"  => "appfinanzas.local",
                            "iat"  => time(),
                            "nbf"  => time(),
                            "exp"  => time() + (3600), // Una hora
                            "data" => [
                                "id"       => $this->userid,
                                "email" => $this->email,
                                "role"     => $this->rol
                            ]
                        ];
                        // Genera el JWT usando HS256
                        $this->jwt = JWT::encode($payload, $this->claveJwt, 'HS256');
                        // Fijamos la bandera a true
                        $valido = true;
                    } else {
                        $this->estado = "Los datos introducidos no son correctos"; // La clave es incorrecta pero el mensaje es ambiguo para dificultar ingeniería inversa
                    }
                } else {
                    $this->estado = "Los datos introducidos no son correctos"; // El usuario es incorrecto pero el mensaje es ambiguo para dificultar ingeniería inversa
                }
            } else {
                $this->estado = "El email para login está vacío";
            }

        } catch(Exception $e){
            // Manejamos el error al operar en la base de datos
            $this->estado = "Error al intentar obtener los datos de usuario: Error: " . $e->getMessage();
        }
        return $valido;
    }

    // Funcion para registrar usuarios en la base de datos
    function registrar(){
        $valido = false;
        // Obtenemos los datos de la solicitud
        $nombre = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->nombre));
        $apellidos = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->apellidos));
        $email = filter_var($this->datosSolicitud->email, FILTER_SANITIZE_EMAIL);
        $telefono = preg_replace('([^0-9])u', '', htmlspecialchars($this->datosSolicitud->telefono));
        $passwordHashed = password_hash($this->datosSolicitud->password, PASSWORD_BCRYPT);
        // Damos valor a los campos restantes que no dependen del formulario
        $rol = "user";
        $fecha_creacion = date("Y-m-d H:i:s");
        $fecha_login = date("Y-m-d H:i:s");
        $activo = true;
        
        // Comprobamos que el usuario no existe ya en la base de datos
        $solicitud = $this->dbo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?;");
        $solicitud->execute([$email]);
        $idusuario = $solicitud->fetch();
        if (!$idusuario){
            // Introduzco los valores en la base de datos
            $consulta = "INSERT INTO usuarios (nombre, apellidos, email, telefono, password_hash, rol, fecha_creacion, fecha_login, activo) 
                        VALUES (?,?,?,?,?,?,?,?,?);";
            $solicitud = $this->dbo->prepare($consulta);
            $solicitud->execute([$nombre, $apellidos, $email, $telefono, $passwordHashed, $rol, $fecha_creacion, $fecha_login, $activo]);
            $valido = true;
        } else {
            // Guardo el error
            $this->estado = "El usuario ya existe";
        }
        return $valido;
    }

    // Funcion para modificar usuarios en la base de datos
    function modificarUsuario(){
        $valido = false;
        if ($this->sesionValida){ 
            // Obtenemos los datos a modificar
            $nombre = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->nombre));
            $apellidos = preg_replace('([^\p{L}0-9 _/\\\&-])u', '', htmlspecialchars($this->datosSolicitud->apellidos));
            $email = filter_var($this->datosSolicitud->email, FILTER_SANITIZE_EMAIL);
            $telefono = preg_replace('([^0-9])u', '', htmlspecialchars($this->datosSolicitud->telefono));
            $passwordHashed = password_hash($this->datosSolicitud->password, PASSWORD_BCRYPT);
            // Damos valor a los campos restantes que no dependen del formulario
            //$rol = "user";
            //$fecha_creacion = date("Y-m-d H:i:s");
            //$fecha_login = date("Y-m-d H:i:s");
            // Preparamos los campos para la consulta
            $campos = [];
            $parametros = [];
            // Cumplimentamos los arrays 
            $campos[] = "nombre = :nombre";
            $parametros["nombre"] = $nombre;
            $campos[] = "apellidos = :apellidos";
            $parametros["apellidos"] = $apellidos;
            $campos[] = "email = :email";
            $parametros["email"] = $email;
            $campos[] = "telefono = :telefono";
            $parametros["telefono"] = $telefono;
            if (!empty($this->datosSolicitud->password)){
                $campos[] = "password_hash = :passwordHased";
                $parametros["passwordHased"] = $passwordHased;
            }
            $parametros["id"] = $this->userid;            
            
            // Introduzco los valores en la base de datos
            //$consulta = "UPDATE usuarios SET nombre=?, apellidos=?, email=?, telefono=?, password_hash=?, rol=?, fecha_creacion=?, fecha_login=? WHERE id_usuario=?;";
            $consulta = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id_usuario=:id;";
            $solicitud = $this->dbo->prepare($consulta);
            //$solicitud->execute([$nombre, $apellidos, $email, $telefono, $passwordHashed, $rol, $fecha_creacion, $fecha_login, $this->userid]);
            $solicitud->execute($parametros);
            $valido = true;
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }
    // Funcion para leer usuarios
    public function leerUsuario(){
        $valido = false;
        if ($this->sesionValida){
            try{
                // Preparamos la consulta para obtener los datos de usuario en caso de que la clave sea válida
                $consulta = "SELECT * FROM usuarios WHERE id_usuario = ?";
                $solicitud = $this->dbo->prepare($consulta);
                // Lanzamos la consulta
                $solicitud->execute([$this->userid]);
                // Obtenemos los resultados asociados a sus nombres de columna
                $datos = $solicitud->fetch();
                // comprobamos que hay datos
                if ($datos){
                    $this->estado = "Se han leido los datos";
                    //Obetenemos los datos y los almacenamos en el modelo
                    $this->userid        = $datos["id_usuario"];
                    $this->nombre        = $datos["nombre"];
                    $this->apellidos     = $datos["apellidos"];
                    $this->email         = $datos["email"];
                    $this->telefono      = $datos["telefono"];
                    $this->rol           = $datos["rol"];
                    $this->fechaCreacion = $datos["fecha_creacion"];
                    $this->fechaLogin    = $datos["fecha_login"];
                    
                    $valido = true;
                } else {
                    $this->estado = "No hay datos para el usuario: " . $this->userid; 
                }
            } catch(Exception $e){
                // Manejamos el error al operar en la base de datos
                $this->estado = "Error al intentar obtener los datos de usuario: Error: " . $e->getMessage();
            }
        } else {
            $this->estado = "No hay sesion válida. No tienes acceso a este recurso";
        }
        return $valido;
    }

    // Getters and Setters
    function getDatosUsuario(){
        $datosUsuario = [
            "userid"        => $this->userid,       
            "nombre"        => $this->nombre,       
            "apellidos"     => $this->apellidos,    
            "email"         => $this->email,        
            "telefono"      => $this->telefono,     
            "rol"           => $this->rol,          
            "fechaCreacion" => $this->fechaCreacion,
            "fechaLogin"    => $this->fechaLogin,
            "token"         => $this->jwt
        ];
        return $datosUsuario;
    }

    function getEstado(){
        return $this->estado;
    }

    function getUserId(){
        return $this->userid;
    }
}
?>