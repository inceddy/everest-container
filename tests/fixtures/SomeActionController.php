<?php

class SomeActionController {
	public function actionOne($dep1, $dep2)
	{
		return $dep1 . ' ' . $dep2;
	}

	public function actionTwo($dep1, $dep2)
	{
		return $dep1 . ' '. $dep2;
	}
}

class SomeActionControllerProvider implements \Everest\Container\FactoryProviderInterface {

	private $factor = 2;

	public function getFactory()
	{
		return [$this, 'factory'];
	}

	public function factory()
	{
		return new SomeActionController();
	}
}