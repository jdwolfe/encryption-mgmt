<?php

require( __DIR__ . '/../app/SkmsClass.php');

$request = $_POST;

$skms = new SkmsController;
$skms->Encrypt( $request );

