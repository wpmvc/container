<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class ServiceWithNestedDependency {
    public $service;

    public function __construct( ServiceWithDependency $service ) {
        $this->service = $service;
    }
}
