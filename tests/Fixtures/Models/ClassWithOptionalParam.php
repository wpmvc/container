<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class ClassWithOptionalParam {
    public $value;

    public function __construct( $value = 'default' ) {
        $this->value = $value;
    }
}
