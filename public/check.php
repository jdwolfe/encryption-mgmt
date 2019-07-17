<?php

require( __DIR__ . '/../app/KmsClass.php');

$KeyId = isset( $_POST['KeyId'] ) ? $_POST['KeyId'] : '';

if( '' != $KeyId ) {
	$kms = new KmsClass;
	$return = $kms->checkKeyId( $KeyId );
	if( $return ) {
		echo '{"KeyCheck": true}';
	} else {
		echo '{"KeyCheck": false}';
	}
} else {
	echo '{"KeyCheck": false}';
}
