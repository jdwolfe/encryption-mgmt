<?php

require( __DIR__ . '/../app/KmsClass.php');

$request = $_POST;

$kms = new KmsClass;
$keyId = $kms->createKey( $request );
echo '{"keyId": "' . $keyId . '"}';
