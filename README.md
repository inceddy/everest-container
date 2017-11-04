# Everest - Container
This Everest component handles Dependency Injection.
It's inspired by the AngularJS injector and Pimple\Container.

## Usage
```PHP
use Everest\Container\Container;

$container = (new Container())
	->value('factor', 2)
	->service('multiplier', ['factor', 'Vendor\\Project\\Multiplier'])
	->factory('double', ['multiplier', function($theMultiplierService){
		return function($number) use ($theMultiplierService) {
			return $multiplierServcie->multiply($number);
		};
	}]);

echo $container['factor']; // 2
echo $container['double'](10); // 20
```
## Injection
Dependencies can be injected into services and factories using a *dependency array* `['dependencyA', 'dependencyB', $callableOrClassname]` where the dependecies will be given to the callable or the class constructor as arguments.

```PHP

function some_function($A) {
	echo "function: $A";
}

class Foo {
	public static function bar($A) {
		echo "static method: $A";
	}

	public function baz($A) {
		echo "method: $A";
	}
}

$object = new Foo;

// Setup container

$container = (new Container)
	// Add some content
	->value('A', 'Some value')
	->value('InnerCallbackObject', $object)
	->value('InnerCallbackClosure', function($A){
		echo "inner: $A";
	})

	// Case 1: Closure
	->factory(['A', function($A) {
			echo "closure $A";
		}])

	// Case 2: Function
	->factory('Function', ['A', 'some_function'])

	// Case 3: Static method
	->factory('Static1',  ['A', [Foo::CLASS, 'bar']])

	// Case 4: Static method variant
	->factory('Static2',  ['A', Foo::CLASS . '::bar'])

	// Case 5: Public method
	->factory('Public',   ['A', [$object, 'baz']])

	// Case 7: Container internal callback object
	->factory('Inner',    ['A', ['InnerCallbackObject', 'baz']])

	// Case 7: Container internal callback closure
	->factory('Inner',    ['A', ['InnerCallbackClosure']])
```

A (slower) way is using the parameter names of the callable or constructor to specify the dependencies. E.g. `function($dependencyA, $dependencyB) {...}` has the same result as `['dependencyA', 'dependencyB', function($depA, $depB) { /*...*/ }]`.

*Note: This does not work with inner callbacks!*

### Constant
Constants can be defined using the `self Everest\Container\Container::constant(string $name, mixed $value)`-method.

*Note: Constants are available during the provider configation cycle!*

### Values
Values can be defined using the `self Everest\Container\Container::value(string $name, mixed $value)`-method.

```PHP
$container = (new Container)
	->value('A', 'Value');
```

### Factory
Factorys can be defined using the `self Everest\Container\Container::factory(string $name, mixed $factory`-method.

```PHP
$container = (new Container)
	->value('DependencyA', 'Value')

	// With dependency hint
	->factory('Name', ['DependencyA', function($a) {
		echo $a; // Value
	}])

	// Auto resolve
	->factory('Name', function($DependencyA) {
		echo $DependencyA; // Value
	}]);
```

### Service
Services can be defined using the `self Everest\Container\Container::service(string $name, mixed $service)`-method.
The service-method expects a class name or a dependency array with the class name as last element as argument. E.g. `['dependencyA', 'dependencyB', 'Vendor\\Project\\Service']` or just (slower) `'Vendor\\Project\\Service'` where the parameter names of the constructor are used to inject the dependencies. 

```PHP

class Foo {
	public function __construct($DependencyA) {
		echo $DependencyA; // Value
	}
}

$container = (new Container)
	->value('DependencyA', 'Value')

	// With dependency hint
	->factory('Name', ['DependencyA', Foo::CLASS])
	
	// Auto resolve
	->factory('Name', Foo::CLASS);
```

### Decorator
You can use the `self Everest\Container\Container::decorator(string $name, mixed $decorator)`-method to overload existing dependencies while receiving the original instance as local dependency. Decorators MUST be a `factory` or a `provider`.

```PHP
$container = (new Container)
	->factory('SomeName', [function(){
		return 'Hello';
	}])
	->decorator('SomeName', ['DecoratedInstance', function($org) {
		return $org . 'World';
	}]);

echo $container['SomeName']; // HelloWorld
```

### Provider
A provider can be any object having the public property `factory` describing the factory as *dependency array*. A provider can be set using the `self Everest\Container\Container::provider(string $name, object $provider)`-method.

Providers can be accessed during configuration process by using their name with `Provider` suffix as dependency.

```PHP
class PrefixerProvider {
	private $prefix = 'Hello';

	public $factory;

	public function __construct()
	{
		$this->factory = ['Name', [$this, 'factory']];
	}

	public function setPrefix(string $prefix) : void
	{
		$this->prefix = $prefix;
	}

	public function factory(string $name) : string
	{
		return sprtinf('%s %s', $this->prefix, $name);
	}
}


$container = (new Container)
	->factory('Name', [function(){
		return 'Justus';
	}])
	->provider('PrefixedName', new PrefixerProvider))
	->config(['PrefixedNameProvider', function($provider) {
		$provider->setPrefix('Goodbye');
	}]);

echo $container['PrefixedName']; // Goodbye Justus
```