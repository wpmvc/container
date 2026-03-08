<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class CircularA {
    public function __construct( CircularB $b ) {
    }
}
