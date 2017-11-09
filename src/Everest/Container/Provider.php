<?php

/*
 * This file is part of Everest.
 *
 * (c) 2017 Philipp Steingrebe <philipp@steingrebe.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Everest\Container;

class Provider implements FactoryProviderInterface {

	public function __construct($factory)
	{
		$this->factory = $factory;
	}

	/**
	 * {@inheritDoc}
	 */
	
	public function getFactory()
	{
		return $this->factory;
	}
}