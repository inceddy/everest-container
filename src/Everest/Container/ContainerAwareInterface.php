<?php

/*
 * This file is part of Everest.
 *
 * (c) 2018 Philipp Steingrebe <philipp@steingrebe.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Everest\Container;

interface ContainerAwareInterface {

	/**
	 * Sets container for this instance
	 * @param Everest\Container\Container $container
	 * @return void
	 */
	
	public function setContainer(Container $container) : void;

}
