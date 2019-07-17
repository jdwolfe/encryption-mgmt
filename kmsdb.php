<?php
class KmsDb extends SQLite3 {
	function __construct() {
		$this->open('./kms1.db');
	}
}


