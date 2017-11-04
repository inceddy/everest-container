<?php

class SomeService {

	public $injectedValue;

	public function __construct($aValue) {
		$this->injectedValue = $aValue;
	}
}