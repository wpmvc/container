<?php
/**
 * ExtensionTaggingTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteClass;
use WpMVC\Container\Tests\Fixtures\Models\ServiceWithDependency;

/**
 * Class ExtensionTaggingTest
 *
 * Verifies the container's tagging system, used for grouping and retrieving multiple
 * related services (e.g., for plugin extensions or middleware).
 *
 * @package WpMVC\Container\Tests\Integration
 */
class ExtensionTaggingTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    /**
     * Verifies basic tagging and retrieval of multiple services.
     */
    public function test_it_can_tag_and_retrieve_services() {
        $this->container->singleton( 'service1', ConcreteClass::class );
        $this->container->singleton( 'service2', ConcreteClass::class );
        
        $this->container->tag( ['service1', 'service2'], 'test_tag' );

        $tagged = $this->container->tagged( 'test_tag' );
        
        $this->assertCount( 2, $tagged );
        $this->assertInstanceOf( ConcreteClass::class, $tagged[0] );
        $this->assertInstanceOf( ConcreteClass::class, $tagged[1] );
    }

    /**
     * Verifies that a single service can be assigned multiple tags.
     */
    public function test_it_supports_multi_tagging() {
        $this->container->singleton( 'service', ConcreteClass::class );
        $this->container->tag( 'service', ['tag1', 'tag2'] );

        $this->assertCount( 1, $this->container->tagged( 'tag1' ) );
        $this->assertCount( 1, $this->container->tagged( 'tag2' ) );
        
        $this->assertInstanceOf( ConcreteClass::class, $this->container->tagged( 'tag1' )[0] );
    }

    /**
     * Verifies that tagged services have their dependencies correctly autowired upon retrieval.
     */
    public function test_it_resolves_complex_services_via_tags() {
        $this->container->singleton( 'complex', ServiceWithDependency::class );
        $this->container->tag( 'complex', 'app.services' );

        $services = $this->container->tagged( 'app.services' );
        
        $this->assertInstanceOf( ServiceWithDependency::class, $services[0] );
        $this->assertInstanceOf( ConcreteClass::class, $services[0]->dependency );
    }

    /**
     * Verifies that aliases can be used when tagging services.
     */
    public function test_it_resolves_aliases_in_tags() {
        $this->container->singleton( ConcreteClass::class );
        $this->container->alias( ConcreteClass::class, 'my_alias' );
        
        $this->container->tag( 'my_alias', 'aliased_tag' );

        $tagged = $this->container->tagged( 'aliased_tag' );
        
        $this->assertCount( 1, $tagged );
        $this->assertInstanceOf( ConcreteClass::class, $tagged[0] );
    }
}
