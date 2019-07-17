<?php

require( __DIR__ . '/../app/KmsClass.php');

$request = $_POST;

$kms = new KmsClass;
$kms->Decrypt( $request );

