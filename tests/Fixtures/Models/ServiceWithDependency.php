<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class ServiceWithDependency {
    public $dependency;

    public function __construct( ConcreteClass $dependency ) {
        $this->dependency = $dependency;
    }
}
