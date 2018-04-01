<?php

use Everest\Container\Container;
use Everest\Container\Injector;

require_once __DIR__ . '/fixtures/SomeDecorator.php';
require_once __DIR__ . '/fixtures/SomeService.php';
require_once __DIR__ . '/fixtures/SomeFactory.php';
require_once __DIR__ . '/fixtures/SomeProviderWithOptions.php';
require_once __DIR__ . '/fixtures/SomeActionController.php';
require_once __DIR__ . '/fixtures/Foo.php';
require_once __DIR__ . '/fixtures/FooContainerAware.php';

/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class ContainerTest extends PHPUnit\Framework\TestCase {

  static function buildContainer()
  {
    return new Container();
  }

  public function testConstant()
  {
    $gotCalled = false;

    $container = $this->buildContainer()->constant('c', 1);

    $container->config(['c', function($c) use (&$gotCalled) {
      $this->assertEquals(1, $c);
      $gotCalled = true;
    }]);

    $this->assertEquals(1, $container['c']);
    $this->assertTrue($gotCalled);
  }

  public function testValueWithString()
  {
    $container = $this->buildContainer()->value('test', 'test');
    $this->assertEquals($container['test'], 'test');
  }

  public function testIsset()
  {
    $container = $this->buildContainer()->value('aValue', null);

    $this->assertTrue(isset($container['aValue']));
    $this->assertFalse(isset($container['aOtherValue']));
  }

  public function testInjectionWithDependeciesArray()
  {
    $container = $this->buildContainer()->value('aValue', 'Test');

    $container->factory('aFactory', ['aValue', function($aOtherValue) {
      return $aOtherValue;
    }]);

    $this->assertEquals($container['aFactory'], 'Test');
  }

  public function testInjectionWithParameterName()
  {
    $container = $this->buildContainer()->value('aValue', 'Test');

    $container->factory('aFactory', function($aValue) {
      return $aValue;
    });

    $this->assertEquals($container['aFactory'], 'Test');    
  }

  /**
     * @expectedException \Exception
     * @expectedExceptionMessage Provider for 'UnknownKey' not found
     */
  
  public function testDependenyNotFound()
  {
    $container = $this->buildContainer();
    $container['UnknownKey'];
  }

  public function testService()
  {
    $container = $this->buildContainer()
      ->value('aValue', 'The value')
      ->service('aService', 'SomeService');

    $this->assertEquals($container['aService'], $container['aService']);
    $this->assertTrue($container['aService'] instanceof SomeService);
    $this->assertEquals($container['aService']->injectedValue, 'The value');
  }

  public function testFactoryWithClosureAndParameter()
  {
    $value = 'The value';
    $container = $this->buildContainer()
      ->value('aValue', $value)
      ->factory('aFactory', function($aValue) {
        return $aValue;
      });

    $this->assertEquals($container['aFactory'], $value);
  }

  public function testFactoryWithClosureAndDepedencyArray()
  {
    $value = 'The value';
    $container = $this->buildContainer()
      ->value('aValue', $value)
      ->factory('aFactory', ['aValue', function($aOtherValue){
        return $aOtherValue;
      }]);

    $this->assertEquals($container['aFactory'], $value);
  }

  public function testFactoryWithCallableArrayAndParameter()
  {
    $value = 'The value';
    $factory = new SomeFactory();
    $container = $this->buildContainer()
      ->value('aValue', $value)
      ->factory('aFactory', [$factory, 'someMethod']);

    $this->assertEquals($container['aFactory'], $value);
  }

  public function testProvider()
  {
    $container = $this->buildContainer()
      ->provider('multiplier', new SomeProviderWithOptions())
      ->config(['multiplierProvider', function($provider){
        $provider->setFactor(10);
      }]);

    $this->assertEquals($container['multiplier'](10), 100);
  }

  public function testDecoratorWithFactory()
  {
    $container = $this->buildContainer()
      ->value('SomeValue', 'Hello')
      ->decorator('SomeValue', ['DecoratedInstance', function($instance) {
        return $instance . 'World';
      }]);

    $this->assertEquals('HelloWorld', $container['SomeValue']);
  }

  public function testDecoratorWithProvider()
  {
    $container = $this->buildContainer()
      ->value('SomeValue', 'Hello')
      ->decorator('SomeValue', new SomeDecorator);

    $this->assertEquals('HelloWorld', $container['SomeValue']);
  } 

  public function testConfig()
  {
    $container = $this->buildContainer()
      ->value('Test', 'Value')
      ->config(['TestProvider', function($testProvider){

      }])
      ->boot();
  }

  public function testLateBind()
  {
    $container = $this->buildContainer()
      // Some simple values
      ->value('Dep1', 'A')
      ->value('Dep2', 'B')

      // Some provider
      ->provider('Controller', new SomeActionControllerProvider())

      // Some factory
      ->factory('ControllerFactory', [function() {
        return function ($a, $b) {
          return "$a $b";
        };
      }])

      // Some value
      ->value('ControllerValue', function ($a, $b) {
        return "$b $a";
      })

      // Some factory using a provider function as factory
      ->factory('Action1', ['Dep1', 'Dep2', ['Controller', 'actionOne']])
      ->factory('Action2', ['Dep2', 'Dep1', ['Controller', 'actionTwo']])
      ->factory('Action3', ['Dep1', 'Dep2', ['ControllerFactory']])
      ->factory('Action4', ['Dep1', 'Dep2', ['ControllerValue']]);

    $this->assertEquals($container['Action1'], 'A B');
    $this->assertEquals($container['Action2'], 'B A');
    $this->assertEquals($container['Action3'], 'A B');
    $this->assertEquals($container['Action4'], 'B A');
  }

  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testInvalidLateBind()
  {
    $container = $this->buildContainer()
      // Some simple values
      ->value('Dep1', 'A')
      ->value('Dep2', 'B')
      ->value('ControllerValue', 'no-callable')

      ->factory('Action', ['Dep1', 'Dep2', ['ControllerValue']]);

    $container['Action'];
  }

  /**
   * @expectedException LogicException
   */
  
  public function testRingDependencies()
  {
    $container = $this->buildContainer()
      ->factory('A', ['B', function($b){}])
      ->factory('B', ['A', function($a){}]);


    $container['A'];
  }

  public function testParameterExtraction() {
    $foo = new Foo;
    $tests = [
      'Closure'           => function($A, $B, $C) {},
      'ClassName'         => Foo::CLASS,
      'ClassName::method' => Foo::CLASS . '::bar',
      'ClassName, method' => [Foo::CLASS, 'bar'],
      'Instance, method'  => [$foo, 'bar']
    ];

    foreach ($tests as $name => $scenario) {
      $this->assertEquals(['A', 'B', 'C', $scenario], Container::getDependencyArray($scenario));
    }
  }

  /**
   * @expectedException InvalidArgumentException
   */
  
  public function testParameterExtractionFailure()
  {
    Container::getDependencyArray('string-no-argument');
  }

  public function testInjectorAndContainerAreInjectable()
  {
    $gotCalled = false;

    $container = $this->buildContainer()
      ->factory('Test', ['Injector', 'Container', function($injector, $container) use (&$gotCalled) {
        $this->assertInstanceOf(Container::CLASS, $container);
        $this->assertInstanceOf(Injector::CLASS, $injector);

        $gotCalled = true;
      }]);

    $container['Test'];

    $this->assertTrue($gotCalled);
  }

  public function testOffsetSet()
  {
    $container = $this->buildContainer();
    $container['Test'] = 'Value';

    $this->assertEquals('Value', $container['Test']);
  }

  public function testMagicSetterAndGetter()
  {
    $container = $this->buildContainer();
    $container->Test = 'Value';

    $this->assertEquals('Value', $container->Test);
  }

  /**
   * @expectedException Exception
   */
  
  public function testOffsetUnsetIsNotImplementes()
  {
    $container = $this->buildContainer();
    unset($container['Test']);
  }

  public function testImport()
  {
    $called = false;
    $container = (new Container)
      ->factory('C', ['A', 'B', function($a, $b) use (&$called){
        $this->assertSame($a, 'Foo');
        $this->assertSame($b, 'Bar');
        $called = true;
        return true;
      }]);

    $crate = (new Container)
      ->value('A', 'Foo')
      ->value('B', 'Bar');

    $container->import($crate, 'Sub');
    $container->import($crate);

    $this->assertSame('Foo', $container['A']);
    $this->assertSame('Bar', $container['Sub/B']);
    $this->assertTrue($container['C']);

    $this->assertTrue($called);
  }

  public function testLazyLoad()
  {
    $container = new Container;
    $container->value('A', 'Foo');

    $this->assertSame('Foo', $container['lazy::A']());
  }

  public function testContainterAware()
  {
    $container = new Container;
    $container->value('A', 'Foo');
    $container->value('B', 'Bar');

    $container->factory('Factory', [function() { return new FooContainerAware;}]);
    $container->service('Service', [FooContainerAware::CLASS]);

    $foo1 = $container['Factory'];
    $this->assertInstanceOf(FooContainerAware::CLASS, $foo1);
    $this->assertEquals(['Foo', 'Bar'], $foo1->require('A', 'B'));

    $foo2 = $container['Service'];
    $this->assertInstanceOf(FooContainerAware::CLASS, $foo2);
    $this->assertEquals(['Foo', 'Bar'], $foo2->require('A', 'B'));
  }
}