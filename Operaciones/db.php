<?php
class DBO{
    private $error;
    private $hostBaseDatos;

    function __construct(){
        $this->hostBaseDatos = getenv('DB_HOST');
        $error = null;
    }

    public function conectar($dbtouse){
        // Cuando contenedoricemos usaremos variables de entorno que se rellenan desde el manifiesto
        $hostBaseDatos=$this->hostBaseDatos;
		$usuario=getenv('DB_USER');
		$clave=getenv('DB_PASSWORD');
        $charset = "utf8mb4";

        // Cadena con los datos de conexión para generar el PDO para manejar la conexión con la base de datos
        $dsn = "mysql:host=$hostBaseDatos;dbname=$dbtouse;charset=$charset";
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        // Creamos la conexión y obtenemnos el objeto manejador
        try{
            $link = new PDO($dsn, $usuario, $clave, $opciones);

        } catch (\PDOException $e){
            // Control de errores de la conexión a la bbdd
            //throw new \PDOException($e->getMessage(), (int)$e->getCode());
            $this->error = $e->getMessage();
        }
		return($link);
    }

    // Getters and setters
    function getDbName(){
        return $this->hostBaseDatos;
    }
    function getError(){
        return $this->error;
    }
}
?>