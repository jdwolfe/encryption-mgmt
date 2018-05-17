<?php

require( __DIR__ . '/../app/SkmsClass.php');

$KeyId = isset( $_POST['KeyId'] ) ? $_POST['KeyId'] : '';

if( '' != $KeyId ) {
	$skms = new SkmsController;
	$return = $skms->checkKeyId( $KeyId );
	echo '{"key": "' . $return . '"}';
} else {
	echo '{}';
}
