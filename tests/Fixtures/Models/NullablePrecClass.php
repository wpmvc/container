<?php
namespace WpMVC\Container\Tests\Fixtures\Models;

class NullablePrecClass {
    public $dep;

    public function __construct( ?ConcreteClass $dep = null ) {
        $this->dep = $dep;
    }
}
