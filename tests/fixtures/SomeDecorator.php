<?php

class SomeDecorator implements \Everest\Container\FactoryProviderInterface {

	private $factor = 2;

	public function getFactory()
	{
		return ['DecoratedInstance', [$this, 'factory']];
	}

	public function factory($instance)
	{
		return $instance . 'World';
	}
}