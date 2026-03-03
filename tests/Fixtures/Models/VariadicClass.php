<?php
namespace WpMVC\Container\Tests\Fixtures\Models;

class VariadicClass {
    public $args;

    public function __construct( ...$args ) {
        $this->args = $args;
    }
}
