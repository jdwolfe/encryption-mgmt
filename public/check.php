<?php

require( __DIR__ . '/../app/SkmsClass.php');

$KeyId = isset( $_POST['KeyId'] ) ? $_POST['KeyId'] : '';

if( '' != $KeyId ) {
	$skms = new SkmsClass;
	$return = $skms->checkKeyId( $KeyId );
	if( $return ) {
		echo '{"KeyCheck": true}';
	} else {
		echo '{"KeyCheck": false}';
	}
} else {
	echo '{"KeyCheck": false}';
}
