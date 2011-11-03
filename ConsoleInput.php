<?php

class ConsoleInput {
  
	protected $_input;
  
	public function __construct($handle = 'php://stdin') {
		$this->_input = fopen($handle, 'r');
	}
  
	public function read() {
		return fgets($this->_input);
	}
}

?>