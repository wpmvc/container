<?php
namespace WpMVC\Container\Tests\Fixtures\Models;
class MixedDependencyClass {
    public $concrete;

    public $value;

    public function __construct( ConcreteClass $concrete, $value ) {
        $this->concrete = $concrete;
        $this->value    = $value;
    }
}
