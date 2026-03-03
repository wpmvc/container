<?php
namespace WpMVC\Container\Tests\Fixtures\Models;
class Level2 {
    public $level3;

    public function __construct( Level3 $level3 ) {
        $this->level3 = $level3; }
}
