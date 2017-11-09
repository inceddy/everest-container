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

class SomeProviderWithOptions implements \Everest\Container\FactoryProviderInterface {

	private $factor = 1;

	public function setFactor($factor) {
		$this->factor = $factor;
	}

	public function getFactory()
	{
		return [$this, 'factory'];
	}

	public function factory()
	{
		return new Multiplier($this->factor);
	}
}