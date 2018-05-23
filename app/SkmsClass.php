<?php
/*
* Encryption/Decryption module for Sonnet
* version: 3
* comment: written for PHP 7
*	   if the KeyId is not in the DB it will call to the version 2 server
*/
require( __DIR__ . '/../config.php' );
require( __DIR__ . '/../skmsdb.php' );
date_default_timezone_set('America/Chicago');

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

	/*
	* Create the KeyId, checks for a unique KeyId, then create a random string to be used as the key
	*/
	public function createKey($request) {

		$db = new SkmsDb();

		$company_id = intval( $request['company_id'] );
		if( 0 == $company_id ) {
			return NULL;
		}

		$keyId_created = FALSE;
		while( !$keyId_created ) {
			$rand = bin2hex( openssl_random_pseudo_bytes( 16 ) );
			$keyId = substr( $rand, 0, 8 ) . '-' . substr( $rand, 8, 4 ) . '-' . substr( $rand, 12, 4 ) . '-' . substr( $rand, 16, 4 ) . '-' . substr( $rand, 20, 12 );
			$query = "SELECT keyid FROM keyids WHERE keyid='" . $keyId . "'";
			$res = $db->query( $query );
			$row = $res->fetchArray(SQLITE3_ASSOC);
			if( FALSE === $row ) {
				$keyId_created = TRUE;
			}
		}

		$key = openssl_random_pseudo_bytes( 32 );
		$key_64 = base64_encode( $key  );

		$insert = "INSERT INTO keyids (keyid, keyvalue, company_id) VALUES( '" . $keyId . "', '" . $key_64 . "', " . $company_id . ")";
		$db->exec( $insert );
		$db->close();
		return $keyId;
	}

	public function encryptDataRemote( $data = array() ) {
echo ENVIRONMENT;

		if ( 'PRODUCTION' == ENVIRONMENT ) {
			$skms_url = 'https://noon.palinode.io/skms';
		} else {
			$skms_url = 'https://dev.palinode.io/skms';
		}

		$encrypt_url = $skms_url . '/encrypt.php';
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $encrypt_url );
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$result = curl_exec( $ch );
		$info = curl_getinfo( $ch );
		curl_close( $ch );
		if( 200 != $info['http_code'] ) {
			return NULL;
		}

		$encrypted = json_decode( $result );
		return $encrypted;

	}

	public function decryptDataRemote( $data = array() ) {
		if ( 'PRODUCTION' == ENVIRONMENT ) {
			$skms_url = 'https://noon.palinode.io/skms';
		} else {
			$skms_url = 'https://dev.palinode.io/skms';
		}

		$decrypt_url = $skms_url . '/decrypt.php';
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $decrypt_url );
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$result = curl_exec( $ch );
		$info = curl_getinfo( $ch );
		curl_close( $ch );
		if( 200 != $info['http_code'] ) {
			return NULL;
		}

		$decrypted = json_decode( $result );
		return $decrypted;

	}

}
