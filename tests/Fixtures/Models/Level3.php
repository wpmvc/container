<?php
namespace WpMVC\Container\Tests\Fixtures\Models;
class Level3 {
    public $level4;

    public function __construct( Level4 $level4 ) {
        $this->level4 = $level4; }
}
