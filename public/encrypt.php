<?php

require( __DIR__ . '/../app/SkmsClass.php');

$request = $_POST;

$skms = new SkmsClass;
$skms->Encrypt( $request );

