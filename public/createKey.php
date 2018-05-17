<?php

require( __DIR__ . '/../app/SkmsClass.php');

$request = $_POST;

$skms = new SkmsClass;
$keyId = $skms->createKey( $request );
echo '{"keyId": "' . $keyId . '"}';
