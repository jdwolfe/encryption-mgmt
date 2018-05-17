<?php
class SkmsDb extends SQLite3 {
	function __construct() {
		$this->open('/sonnet/encryption/skms/skms3.db');
	}
}


