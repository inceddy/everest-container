<?php

class SomeDecorator {

	private $factor = 2;

	public function __construct()
	{
		$this->factory = ['DecoratedInstance', [$this, 'factory']];
	}

	public function factory($instance)
	{
		return $instance . 'World';
	}
}