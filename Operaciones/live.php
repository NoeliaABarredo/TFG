<?php
//Para liveness probe
http_response_code(200);

echo json_encode(["status" => "I am alive"]);
?>