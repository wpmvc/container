<?php

namespace WpMVC\Container\Tests\Fixtures\Models;

class CircularB {
    public function __construct( CircularA $a ) {
    }
}
