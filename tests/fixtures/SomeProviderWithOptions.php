<?php

class Multiplier {

	private $factor;

	public function __construct($factor)
	{
		$this->factor = $factor;
	}

	public function __invoke($number) 
	{
		return $this->factor * $number;
	}
}

class SomeProviderWithOptions {

	private $factor = 1;

	public $factory;

	public function setFactor($factor) {
		$this->factor = $factor;
		$this->factory = [$this, 'factory'];
	}

	public function factory()
	{
		return new Multiplier($this->factor);
	}
}