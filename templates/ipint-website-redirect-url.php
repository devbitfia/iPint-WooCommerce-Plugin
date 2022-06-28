<?php 


$request = file_get_contents('php://input');

register_log("Raw Request: ". $request);
register_log("Post Request: ". print_r($_POST, true));
register_log("GET Request: ". print_r($_GET, true));

echo "reached"; die;