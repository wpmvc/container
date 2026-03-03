<?php
namespace WpMVC\Container\Tests\Fixtures\Models;
class Level4 {
    public $level5;

    public function __construct( Level5 $level5 ) {
        $this->level5 = $level5; }
}
