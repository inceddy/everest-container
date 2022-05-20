<?php

use Everest\Container\Injector;
use Everest\Container\Tracer;
use Everest\Container\Cache;

require_once __DIR__ .'/fixtures/Foo.php';


/**
 * @author  Philipp Steingrebe <philipp@steingrebe.de>
 */
class InjectorTest extends PHPUnit\Framework\TestCase {

  public function testHas()
  {
    $cache = new Cache;
    $cache->set('key', 'value');

    $injector = new Injector($cache, function(){}, new Tracer);

    $this->assertTrue($injector->has('key'));
    $this->assertFalse($injector->has('unkownKey'));
  }

  public function testGet()
  {
    $cache = new Cache;
    $cache->set('key', 'value');
    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    $this->assertTrue($injector->has('key'));
    $this->assertFalse($injector->has('unkownKey'));
  }

  public function testInvoke()
  {
    // Depedencies
    $cache = new Cache;
    $cache->set('key', 'value');

    $injector = new Injector($cache, function($key){
      return $this->cache->get($key); 
    }, new Tracer);

    // Usual case
    $injector->invoke(['key', function($value){
      $this->assertEquals('value', $value);
    }]);

    // With local dependencies
    $injector->invoke(['key', 'local', function($value, $local){
      $this->assertEquals('value', $value);
      $this->assertEquals('value2', $local);
    }], ['local' => 'value2']);

    // With local and mandatory dependencies
    $injector->invoke(['key', 'local', function($mand, $value, $local){
      $this->assertEquals('value', $value);
      $this->assertEquals('value2', $local);
      $this->assertEquals('value3', $mand);
    }], ['local' => 'value2'], ['value3']);
  }

  public function testInstantiate()
  {
    // Depedencies
    $cache = new Cache;
    $cache->set('A', 1);
    $cache->set('B', 2);
    $cache->set('C', 3);

    $injector = new Injector($cache, function($key){
      return $this->cache->get($key); 
    }, new Tracer);

    // Usual case
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS]);
    $this->assertEquals(1, $foo->A);
    $this->assertEquals(2, $foo->B);
    $this->assertEquals(3, $foo->C);

    // With local dependencies
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS], ['B' => 4]);
    $this->assertEquals(1, $foo->A);
    $this->assertEquals(4, $foo->B);
    $this->assertEquals(3, $foo->C);

    // With local and mandatory dependencies
    $foo = $injector->instantiate(['A', 'B', 'C', Foo::CLASS], ['B' => 4], [5]);
    $this->assertEquals(5, $foo->A);
    $this->assertEquals(1, $foo->B);
    $this->assertEquals(4, $foo->C);
  }
  
  public function testInstantiateWithUnkownConstructor()
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Instantiation of UnkownClassName not possible.');

    // Depedencies
    $cache = new Cache;
    $cache->set('A', 1);
    $cache->set('B', 2);
    $cache->set('C', 3);

    $injector = new Injector($cache, function($key){
      return $this->cache->get($key); 
    }, new Tracer);

    $foo = $injector->instantiate(['A', 'B', 'C', 'UnkownClassName']);
  }
  
  public function testInstantiateWithInvalidConstructor()
  {
    $this->expectException(\InvalidArgumentException::class);

    // Depedencies
    $cache = new Cache;
    $cache->set('A', 1);
    $cache->set('B', 2);
    $cache->set('C', 3);

    $injector = new Injector($cache, function($key){
      return $this->cache[$key]; 
    }, new Tracer);

    $foo = $injector->instantiate(['A', 'B', 'C', ['Array is not constructable']]);
  }
}