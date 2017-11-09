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

use Closure;
use ArrayAccess;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use Exception;
use InvalidArgumentException;
use LogicException;

/**
 * Extracts the parameter names of the given callable or 
 * of the constructor of the given classname.
 *
 * @param  mixed $callableOrClassname  The callable or the classname
 *
 * @return array<string>               The parameter names or an empty array if nothing was extrected
 * 
 */
	
function extractParameters($callableOrClassname) : array {
	switch (true) {
		// Handle closure
		case $callableOrClassname instanceof Closure:
			$parameters = (new ReflectionFunction($callableOrClassname))->getParameters();
			break;
		
		// Handle clasname-object-method-array
		case is_array($callableOrClassname) && is_callable($callableOrClassname):
			$class = is_string($callableOrClassname[0]) ? $callableOrClassname[0] : get_class($callableOrClassname[0]);
			$parameters = (new ReflectionMethod($class . '::' . $callableOrClassname[1]))->getParameters();
			break;

		// Handle callable-string classname::method or function name
		case is_string($callableOrClassname) && is_callable($callableOrClassname):
			$parameters = strpos($callableOrClassname, '::') ?
								(new ReflectionMethod($callableOrClassname))->getParameters() :
								(new ReflectionFunction($callableOrClassname))->getParameters();
			break;
		
		// Handle class name
		case is_string($callableOrClassname) && class_exists($callableOrClassname):
			$parameters = (new ReflectionMethod($callableOrClassname . '::__construct'))->getParameters();
			break;

		default:
			throw new InvalidArgumentException(sprintf(
				'Cant extract parameters from given argument with type \'%s\'.', 
				is_object($callableOrClassname) ? get_class($callableOrClassname) : gettype($callableOrClassname)
			));
	}

	return array_map(function(ReflectionParameter $parameter){
		return $parameter->name;
	}, $parameters);
}


/**
 * Container class inspired by the dependency injection of the AngularJS Framework.
 * @author Philipp Steingrebe <philipp@steingrebe.de>
 */

class Container implements ArrayAccess {

	/**
	 * Default state after instanciation
	 */
	
	protected const STATE_INITIAL = 0;

	/**
	 * Config state while configurations are running
	 */
	
	protected const STATE_CONFIG  = 10;

	/**
	 * Completely booted
	 */
	
	protected const STATE_BOOTED  = 20;

	
	/**
	 * The cache where all providers are stored
	 * @var ArrayObject
	 */
	
	private $providerCache;


	/**
	 * The cache where all instances are stored
	 * @var ArrayObject
	 */
	
	private $instanceCache;


	/**
	 * Injector used to prepare the providers
	 * @var ieu\Core\Injector
	 */
	
	private $providerInjector;


	/**
	 * Injector used to prepare the instances
	 * @var ieu\Core\Injector
	 */
	
	private $instanceInjector;


	/**
	 * List of configuration functions to setup the container
	 * @var array[]
	 */
	
	private $configs = [];


	/**
	 * The state of this container
	 * @var integer
	 */
	
	protected $state;

	/**
	 * Debugger
	 * @var Everest\Container\Tracer
	 */
	
	public $tracer;


	/**
	 * Creates a new container.
	 */
	
	public function __construct(Container ... $containers)
	{
		// Set container to initial state
		$this->state = self::STATE_INITIAL;

		// Setup cache
		$this->providerCache = new Cache;
		$this->instanceCache = new Cache;

		foreach ($containers as $parentContainer) {
			$this->providerCache->merge($parentContainer->providerCache);
			$this->instanceCache->merge($parentContainer->instanceCache);
		}

		$this->buildInjectors();
	}


	/**
	 * Sets up the provider and instance injectors
	 *
	 * @return void
	 */
	
	private function buildInjectors()
	{
		// Debug tracer
		$this->tracer = new Tracer;

		// Build provider injector
		$providerInjector =
		$this->providerInjector = new Injector(
			// Cache
			$this->providerCache, 
			// Factory
			function($name) {
				$name = substr($name, 0, -8);
				throw new LogicException("Provider for '$name' not found\n" . $this->tracer);
			}, 
			// Debug tracer
			$this->tracer
		);

		// Build instance injector
		$this->instanceInjector = new Injector(
			// Cache
			$this->instanceCache, 
			// Factory
			function($name) use ($providerInjector) {
				$this->tracer->request($name);

				$provider = $providerInjector->get($name . 'Provider');
				// Determin factory dependencies
				$factoryAndDependencies = Container::getDependencyArray($provider->getFactory());
				$instance = $this->invoke($factoryAndDependencies);

				$this->tracer->received($name);

				return $instance;
			}, 
			// Debug tracer
			$this->tracer
		);

		// Implement container as provider
		$this->provider('Container', new Provider([function(){
			return $this;
		}]));

		// Implement instance injector as provider
		$this->provider('Injector', new Provider([function() {
			return $this->instanceInjector;
		}]));
	}


	/**
	 * Imports an other container to this one.
	 *
	 * @param Everest\Container\Container $container
	 *   The container to import
	 * @param string|null $prefix
	 *   The optional prefix to prefix the container keys
	 *
	 * @return self
	 */
	
	public function import(Container $container, string $prefix = null)
	{
		$container->boot();
		$this->providerCache->merge($container->providerCache, $prefix);
		$this->instanceCache->merge($container->instanceCache, $prefix);
		return $this;
	}


	/**
	 * Register a new provider which must implement the
	 * provider interface. 
	 *
	 * Providers can be injected using the provider name
	 * with 'Provider' suffix. Eg. ieuInjectorProvider
	 *
	 * @throws Exception if the container is already bootet.
	 *
	 * @param  string                  $name     The name of the provider
	 * @param  ieu\Core\Provider|array $provider The Provider or an Array with a 'factory' key
	 *
	 * @return self
	 * 
	 */
	
	public function provider(string $name, FactoryProviderInterface $provider)
	{
		$this->providerCache->set($name . 'Provider', $provider);
		return $this;
	}


	/**
	 * Register a new decorator which will overload
	 * the factory of the given provider with the given name.
	 *
	 * The original instance can be injected using
	 * `DecoratedInstance` as depedency.
	 *
	 * @throws Exception if the container is already bootet.
	 *
	 * @param  string $name
	 *    The name to overload
	 * @param  array|callable $decorator 
	 *     The decorator
	 *
	 * @return self
	 * 
	 */

	public function decorator($name, $decorator)
	{
		$lagacyProvider = $this->providerInjector->get($name . 'Provider');
		$lagacyFactory = $lagacyProvider->getFactory();

		// If decorator is provider resolve factory
		if (is_object($decorator) && $decorator instanceof FactoryProviderInterface) {
			$decorator = $decorator->getFactory();
		}

		$decoratorAndDependencies = self::getDependencyArray($decorator);

		// Overload old provider
		return $this->factory($name, function() use ($decoratorAndDependencies, $lagacyFactory) {
			$decoratedInstance = $this->instanceInjector->invoke($lagacyFactory);
			return $this->instanceInjector->invoke(
				$decoratorAndDependencies, 
				['DecoratedInstance' => $decoratedInstance]
			);
		});
	}


	/**
	 * Register a new service.
	 *
	 * @param  string $name     The name of the service
	 * @param  mixed  $service  The service
	 *
	 * @return self
	 * 
	 */
	
	public function service($name, $service)
	{
		$dependenciesAndService = self::getDependencyArray($service);
		
		return $this->provider(
			$name, 
			new Provider(['Injector', function($injector) use ($dependenciesAndService) {
					return $injector->instantiate($dependenciesAndService);
			}])
		);
	}


	/**
	 * Register a new factory
	 *
	 * @param  string $name     The name of the factory
	 * @param  mixed  $factory  The factory
	 *
	 * @return self
	 * 
	 */
	
	public function factory(string $name, $factory)
	{
		return $this->provider($name, new Provider($factory));
	}


	/**
	 * Register a new value
	 *
	 * @param  string $name  The name of the value
	 * @param  mixed  $value The value
	 *
	 * @return self
	 * 
	 */
	
	public function value(string $name, $value)
	{
		return $this->factory($name, [function() use ($value) {
			return $value;
		}]);
	}


	/**
	 * Register a new constant.
	 * Constants are available during configuration state.
	 *
	 * @param  string $name  The name of the constant
	 * @param  mixed  $value The constant
	 *
	 * @return self
	 * 
	 */
	
	public function constant(string $name, $value)
	{
		$this->providerCache->set($name, $value);
		$this->instanceCache->set($name, $value);

		return $this;
	}


	/**
	 * Adds a dependency-callable-array to this comfig stack.
	 * On boot all callables will be called with the given dependencies.
	 *
	 * @param  array  $config  The dependency-callable-array
	 *
	 * @return self
	 * 
	 */
	
	public function config($config)
	{
		$this->configs[] = $config;

		return $this;
	}


	/**
	 * Run all configurations and set container state to `bootet`
	 *
	 * @return self
	 * 
	 */
	
	public function boot()
	{
		$this->state = self::STATE_CONFIG;

		if ($this->state === self::STATE_BOOTED) {
			return $this;
		}

		foreach ($this->configs as $config) {
			$dependenciesAndCallable = self::getDependencyArray($config);

			$callable = array_pop($dependenciesAndCallable);
			$dependencies = array_map([$this->providerInjector, 'get'], $dependenciesAndCallable);

			call_user_func_array($callable, $dependencies);
		}

		$this->state = self::STATE_BOOTED;
		return $this;
	}


	/**
	 * Alias for `Everest\Container\Container::offsetSet()`
	 *
	 * @param string $name
	 *    The name of the value to set
	 * @param mixed $value
	 *    The value to set
	 *
	 * @return self
	 * 
	 */
	
	public function __set(string $name, $value)
	{
		return $this->value($name, $value);
	}


	/**
	 * Alias for `Everest\Container\Container::offsetGet()`
	 *
	 * @param  string $name
	 *    The name of the dependency to get
	 *
	 * @return mixed
	 *    The dependency
	 *    
	 */
	
	public function __get(string $name)
	{
		return $this->offsetGet($name);
	}


	/**
	 * Gets a dependency from the container.
	 * If the container is not yet booted all configs will be run.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of the dependency to get
	 *
	 * @return mixed
	 *    The dependency
	 * 
	 */

	public function offsetGet($name)
	{
		if ($this->state === self::STATE_INITIAL) {
			$this->boot();
		}

		return $this->instanceInjector->get($name);
	}


	/**
	 * Sets container value.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of dependency value to set
	 * @param  mixed $value
	 *    The value to set
	 *
	 * @return self
	 * 
	 */
	
	public function offsetSet($name, $value)
	{
		return $this->value($name, $value);
	}


	/**
	 * Returns whether a dependency instance or 
	 * the corresponding provider exist within
	 * this container.
	 *
	 * Satisfies ArrayAcces interface.
	 *
	 * @param  string $name
	 *    The name of dependency value to check
	 *
	 * @return bool
	 * 
	 */
	
	public function offsetExists($name)
	{
		return $this->instanceInjector->has($name) || 
		       $this->providerInjector->has($name . 'Provider');
	}


	/**
	 * Must exist to satisfy the ArrayAccess interface
	 * but is not implemented.
	 *
	 * @throws Exception allways as this method is not implemented
	 *
	 * @param  string $name
	 *    The name to unset
	 *
	 * @return void
	 * 
	 */
	
	public function offsetUnset($name)
	{
		throw new Exception("Not implemented");		
	}


	/**
	 * Checks if an callable or a classname is wraped in a dependency-factory-array
	 * `['aDependency', 'aOtherDependency', $callableOrClassname]`.
	 * If not the argument will be treated as factory and the dependencys will be
	 * extracted from the function, method or constructor arguments.
	 *
	 * @see Everest\Container\
	 *
	 * @param  mixed $argument  The argument to check whether it is a valid depedency-factory-array
	 *                          or just a factory.
	 *
	 * @return array            The dependency array
	 * 
	 */
	
	public static function getDependencyArray($argument)
	{
		// Must condition: A dependency-factory-array is an array
		if (is_array($argument)) {
			// Must condition: A last element in a dependency-factory-array is
			//                 - an array: [ClassName|Object, Method]
			//                 - an callable: Closure, Invokeable, ClassName::StaticMethod-string
			//                 - an classname
			
			$factory = end($argument);

			if (is_array($factory) || is_callable($factory) || class_exists($factory)) {
				return $argument;
			}
		}

		// Try to extract parameters from argument and combine it to an dependency-factory-array
		$parameter = extractParameters($argument);
		array_push($parameter, $argument);
		return $parameter;
	}
}
