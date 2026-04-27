<?php
// probe
require_once 'db.php';
http_response_code(200);
$retMSG = "";
try {
    $this->dbo = new DBO();
    $this->in = $this->dbo->connect($this-dbo->getDbName());
    $result = $this->in->query("SELECT 1;");
    if ($result === false)
        throw new Exception("DB query failed");
    $retMSG = "DB query Ok";
} catch (Exception $e){
    http_response_code(500);
    $retMSG = "Error: " . $e->getMessage();
}
echo json_encode($retMSG);
?>