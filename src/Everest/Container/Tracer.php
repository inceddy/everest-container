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

class Tracer {

	/**
	 * The action state
	 * @var array
	 */
	
	private $state = [];

	
	/**
	 * Called on instance request
	 *
	 * @param  string $name
	 *    The instance name
	 *
	 * @return void
	 */
	
	public function request($name)
	{
		array_push($this->state, [
			'type' => 'request',
			'name' => $name
		]);
	}


	/**
	 * Callen before resolving the required
	 * dependencies.
	 *
	 * @param  array  $dependencies
	 *    The list of the dependency names
	 *
	 * @return void
	 */
	
	public function dependsOn(array $dependencies)
	{
		array_push($this->state, [
			'type' => 'dependsOn',
			'dependencies' => $dependencies
		]);
	}

	/**
	 * Called to make a annotations during 
	 * the instantiation process.
	 *
	 * @param  string $message
	 *    The message to note
	 *
	 * @return void
	 */
	
	public function note($message)
	{
		array_push($this->state, [
			'type' => 'note',
			'note' => $message
		]);
	}


	/**
	 * Called when instantiation is completed
	 *
	 * @return void
	 */
	
	public function received()
	{
		array_push($this->state, [
			'type' => 'received'
		]);
	}


	/**
	 * Visualises the tracer state.
	 * Usefull to find errors within the
	 * chain of dependency instantiations.
	 *
	 * @return string
	 */
	
	public function __toString()
	{
		$level = 0;
		$lines = [];

		foreach ($this->state as $action) {
			switch ($action['type']) {
				case 'request':
					$lines[] = str_repeat("\t", $level) . $action['name'] . ' requested ('; 
					$level++;
					break;
				case 'dependsOn':
					$deps = $action['dependencies'];
					$lines[] = str_repeat("\t", $level) . 'Depedencies: ' . (empty($deps) ? 'none' : '[' . implode(', ', $deps) . ']'); 
					break;
				case 'note':
					$lines[] = str_repeat("\t", $level) . 'Note: ' . $action['note'];
					break;
				case 'received':
					$level--;
					$lines[] = str_repeat("\t", $level) . ')';
					break;
			}
		}

		return implode("\n", $lines);
	}
}