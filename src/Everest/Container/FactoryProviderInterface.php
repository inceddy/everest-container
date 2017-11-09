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

interface FactoryProviderInterface {
	/**
	 * Should return a dependency array or a callable
	 * @return mixed
	 */
	
	public function getFactory();
}