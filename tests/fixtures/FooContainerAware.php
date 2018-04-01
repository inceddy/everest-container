<?php

class FooContainerAware implements \Everest\Container\ContainerAwareInterface
{
	private $container;

	public function setContainer(\Everest\Container\Container $container) : void
	{
		$this->container = $container;
	}

	public function require(string ... $dependencies) 
	{
		if (count($dependencies) === 1) {
			return $this->container[$dependencies[0]];
		}

		return array_map(function($dependency) {
			return $this->container[$dependency];
		}, $dependencies);
	}
}