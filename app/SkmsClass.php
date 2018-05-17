<?php
/*
* Encryption/Decryption module for Sonnet
* version: 3
* comment: written for PHP 7
*	   if the KeyId is not in the DB it will call to the version 2 server
*/
date_default_timezone_set('America/Chicago');

class SkmsDb extends SQLite3 {
	function __construct() {
		$this->open('/sonnet/encryption/skms/skms3.db');
	}
}


class SkmsClass {

	private $KeyId = NULL;

	private function checkData( $data ) {

		if( !isset( $data['KeyId'] ) ) {
			echo json_encode( array() );
			die();
		}
		$this->KeyId = trim( $data['KeyId'] );
		unset( $data['KeyId'] );

		//check format of $KeyId
		preg_match( '/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $this->KeyId, $matches );
		if( '' == $this->KeyId || count( $matches ) == 0 ) { 
			$this->SkmsLog( $this->KeyId . ',invalid key' );
			echo json_encode( array() );
			die();
		}

		return $data;

	}

	public function getKey( $KeyId = '' ) {

		$db = new SkmsDb();

		$sql = "SELECT keyvalue FROM keyids WHERE keyid = '" . $KeyId . "'";
		$res = $db->query( $sql );
		$row = $res->fetchArray(SQLITE3_ASSOC);
		$db->close();
		$key = $row['keyvalue'];
		if( NULL === $key ) {
			return NULL;
		} else {
			return $key;
		}
		
		die('Can not make new key');
	}

	/*
	* Make a new random key
	*/
	public function createKey( $KeyId = '' ) {
		// make a new key
		$strong = NULL;
		$key = openssl_random_pseudo_bytes(32, $strong);
		$key_64 = base64_encode( $key  );

		$db = new SkmsDb();
		$insert = "INSERT INTO keyids (keyid, keyvalue) VALUES( '" . $KeyId . "', '" . $key_64 . "' )";
		$db->exec( $insert );

		$db->close();
		return $key_64;
	}

	public function encryptLocal ( $key = '', $text = '' ) {

		$key = base64_decode( $key );

		$strong = NULL;
		$iv = openssl_random_pseudo_bytes(32, $strong);

		$enc_text = openssl_encrypt($text, "aes-128-gcm", $key, 0, $iv, $tag);

		$iv_64 = base64_encode( $iv );
		$tag_64 = base64_encode( $tag );
		$enc_text_64 = base64_encode( $enc_text );

		return $iv_64 . '|' . $tag_64 . '|' . $enc_text_64;

	}

	public function decryptLocal ( $key = '', $enc_64 = '' ) {

		$key = base64_decode( $key );

		if( FALSE === strpos( $enc_64, '|' )  ) {
			return NULL;
		}

		$enc_parts = explode( '|', $enc_64 );
		$iv = base64_decode( $enc_parts[0] );
		$tag = base64_decode( $enc_parts[1] );
		$enc_text = base64_decode( $enc_parts[2] );

		$dec_text = openssl_decrypt( $enc_text, "aes-128-gcm", $key, 0, $iv, $tag );
		$dec_text = trim( $dec_text );

		return $dec_text;

	}

	public function Encrypt($request) {
		$start = microtime(1);

		$data = $request;
		$data = $this->checkData( $data );

		$key = $this->getKey( $this->KeyId );
		if( NULL === $key ) {
		// it uses the v2 encryption
			

		} else {

		$encrypted = array();
		foreach( $data as $field => $value ) {
			$value = trim( $value );
			if( '' == $value ) {
				$encrypted[$field] = NULL;
			} else {
				$encrypted[$field] = $this->encryptLocal( $key, $value );
			}
		}

		} // if key is NULL

		$end = microtime(1);
		$encrypted['total_time'] = $end - $start;
		$this->SkmsLog( $this->KeyId . ',' . $encrypted['total_time'] );
		echo json_encode( $encrypted );

		die();

	}

	public function Decrypt($request) {
		$start = microtime(1);

		$data = $request;
		$data = $this->checkData( $data );

		$key = $this->getKey( $this->KeyId );

		$plaintext = array();
		foreach( $data as $field => $value ) {
			$plaintext[$field] = $this->decryptLocal( $key, $value );
		}

		$end = microtime(1);
		$plaintext['total_time'] = $end - $start;
		$this->SkmsLog( $this->KeyId . ',' . $plaintext['total_time'] );
		echo json_encode( $plaintext );

		die();

	}

	private function SkmsLog( $s = '' ) {
		$f = fopen( '/sonnet/encryption/skms/logs/skms.' . date('Ymd') . '.log', 'a' );
		fwrite( $f, date('Y-m-d H:i:s') . ' ' . $s . "\n" );
		fclose( $f );
	}

}
