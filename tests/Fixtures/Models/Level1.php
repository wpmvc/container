<?php
namespace WpMVC\Container\Tests\Fixtures\Models;
class Level1 {
    public $level2;

    public function __construct( Level2 $level2 ) {
        $this->level2 = $level2; }
}
