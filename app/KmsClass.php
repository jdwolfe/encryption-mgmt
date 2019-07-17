<?php
/*
* Encryption/Decryption
* version: 1
* comment: written for PHP 7
*	   if the KeyId is not in the DB it will call to the version 2 server
*/
require( __DIR__ . '/../config.php' );
require( __DIR__ . '/../kmsdb.php' );
date_default_timezone_set('America/Chicago');

class KmsClass {

	private $KeyId = NULL;

	private function checkData( $data ) {

		if( !isset( $data['KeyId'] ) ) {
			return NULL;
			echo json_encode( array() );
			die();
		}
		$this->KeyId = trim( $data['KeyId'] );
		unset( $data['KeyId'] );

		//check format of $KeyId
		preg_match( '/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $this->KeyId, $matches );
		if( '' == $this->KeyId || count( $matches ) == 0 ) { 
			$this->KmsLog( $this->KeyId . ',invalid key' );
			echo json_encode( array() );
			die();
		}

		return $data;

	}

	public function getKey( $KeyId = '' ) {

		$db = new KmsDb();

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

		$encrypted = array();

		$key = $this->getKey( $this->KeyId );
		if( NULL === $key ) {
			// KeyID is not recognized. Send back blank data.
			$this->KmsLog( 'KeyID Not Recongized. ' .  $this->KeyId );
			foreach( $data as $field => $value ) {
				$encrypted[$field] = NULL;
			}
		} else {

			foreach( $data as $field => $value ) {
				$value = trim( $value );
				if( '' == $value || NULL === $value ) {
					$encrypted[$field] = NULL;
				} else {
					$encrypted[$field] = $this->encryptLocal( $key, $value );
				}
			}

		} // if key is NULL

		$end = microtime(1);
		$encrypted['total_time'] = $end - $start;
		$this->KmsLog( $this->KeyId . ',' . $encrypted['total_time'] );
		echo json_encode( $encrypted );

		die();

	}

	public function Decrypt($request) {
		$start = microtime(1);

		$data = $request;
		$data = $this->checkData( $data );

		$key = $this->getKey( $this->KeyId );

		$plaintext = array();

		if( NULL === $key ) {
			// KeyID is not recognized. Send back blank data.
			$this->KmsLog( 'KeyID Not Recongized. ' .  $this->KeyId );
			foreach( $data as $field => $value ) {
				$plaintext[$field] = NULL;
			}
		} else {

			foreach( $data as $field => $value ) {
				if( '' == $value || NULL === $value ) {
					$plaintext[$field] = NULL;
				} else {
					$plaintext[$field] = $this->decryptLocal( $key, $value );
				}
			}

		} // end if key is NULL

		$end = microtime(1);
		$plaintext['total_time'] = $end - $start;
		$this->KmsLog( $this->KeyId . ',' . $plaintext['total_time'] );
		echo json_encode( $plaintext );

		die();

	}

	private function KmsLog( $s = '' ) {
		$f = fopen( './../logs/kms.' . date('Ymd') . '.log', 'a' );
		fwrite( $f, date('Y-m-d H:i:s') . ' ' . $s . "\n" );
		fclose( $f );
	}

	/*
	* Create the KeyId, checks for a unique KeyId, then create a random string to be used as the key
	*/
	public function createKey($request) {

		$db = new KmsDb();

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

	/*
	* Function to check the KeyId
	*/
	public function checkKeyId( $KeyId = '' ) {

		//check format of $KeyId
		preg_match( '/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $KeyId, $matches );
		if( '' == $KeyId || count( $matches ) == 0 ) { 
			$this->KmsLog( $KeyId . ',Invalid KeyId format' );
			return FALSE;
		}

		$key = $this->getKey( $KeyId );
		if( NULL === $key ) {
			$this->KmsLog( $KeyId . ',Unknown KeyId' );
			return FALSE;
		}

		$this->KmsLog( $KeyId . ',KeyId exists' );
		return TRUE;

	}

}

