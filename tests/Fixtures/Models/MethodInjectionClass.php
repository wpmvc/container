<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class MethodInjectionClass {
    public function method( ConcreteClass $dependency, $param ) {
        return ['dependency' => $dependency, 'param' => $param];
    }

    public static function static_method( ConcreteClass $dependency, $param ) {
        return ['dependency' => $dependency, 'param' => $param];
    }
}
