<?php

namespace WpMVC\Container\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;

interface TestInterface {}
class ImplementationA implements TestInterface {}
class ImplementationB implements TestInterface {}

class ClientA {
    public $dep;

    public function __construct( TestInterface $dep ) {
        $this->dep = $dep;
    }
}

class ClientB {
    public $dep;

    public function __construct( TestInterface $dep ) {
        $this->dep = $dep;
    }
}

class ContextualBindingTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_contextual_binding_resolves_correctly() {
        $this->container->when( ClientA::class )
            ->needs( TestInterface::class )
            ->give( ImplementationA::class );

        $this->container->when( ClientB::class )
            ->needs( TestInterface::class )
            ->give( ImplementationB::class );

        $client_a = $this->container->get( ClientA::class );
        $client_b = $this->container->get( ClientB::class );

        $this->assertInstanceOf( ImplementationA::class, $client_a->dep );
        $this->assertInstanceOf( ImplementationB::class, $client_b->dep );
    }

    public function test_contextual_binding_with_closure() {
        $this->container->when( ClientA::class )
            ->needs( TestInterface::class )
            ->give(
                function() {
                    return new ImplementationA();
                }
            );

        $client_a = $this->container->get( ClientA::class );
        $this->assertInstanceOf( ImplementationA::class, $client_a->dep );
    }
}
