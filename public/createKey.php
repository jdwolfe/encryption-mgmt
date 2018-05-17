<?php

require( __DIR__ . '/../app/SkmsClass.php');

$skms = new SkmsController;
$keyId = $skms->createKey();
echo '{"keyId": "' . $keyId . '"}';
