<?php
namespace WpMVC\Container\Tests\Fixtures\Models;

class InvokableClass {
    public function __invoke( ConcreteClass $concrete, $param ) {
        return ['concrete' => $concrete, 'param' => $param];
    }
}
