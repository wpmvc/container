<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class ClassWithParams {
    public $value;

    public $number;

    public function __construct( $value, $number = 0 ) {
        $this->value  = $value;
        $this->number = $number;
    }
}
