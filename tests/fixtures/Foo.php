<?php

class Foo
{
	public $A;

	public $B;

	public $C;

	public function __construct($A = null, $B = null, $C = null) {
		$this->A = $A;
		$this->B = $B;
		$this->C = $C;
	}

	public function bar($A, $B, $C) 
	{
		return func_get_args();
	}
}