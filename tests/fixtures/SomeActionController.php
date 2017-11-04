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

class SomeActionControllerProvider {

	private $factor = 2;

	public $factory;

	public function __construct()
	{
		$this->factory = [$this, 'factory'];
	}

	public function factory()
	{
		return new SomeActionController();
	}
}