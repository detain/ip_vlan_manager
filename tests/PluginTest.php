<?php

declare(strict_types=1);

namespace Detain\IpVlanManager\Tests;

use Detain\IpVlanManager\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Test suite for the Detain\IpVlanManager\Plugin class.
 *
 * The previous contents of this file were a PHPUnit_SkeletonGenerator stub from 2017:
 * three markTestIncomplete() bodies and a PHPUnit 5-era `protected function setUp()`
 * with no `: void`. It asserted nothing and would not even load under PHPUnit 9, so it
 * has been replaced rather than ported method-for-method.
 *
 * getMenu() and every file under src/ reach for \MyAdmin\App and the framework's global
 * helper functions, none of which exist inside this package. Those are therefore
 * examined by reflection and static analysis rather than executed. getRequirements() is
 * the one handler that can be driven for real, because its only collaborator is the
 * loader passed in on the event.
 *
 * @coversDefaultClass \Detain\IpVlanManager\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    // ---------------------------------------------------------------
    // Class structure
    // ---------------------------------------------------------------

    /**
     * @covers ::__construct
     * @return void
     */
    public function testClassIsInstantiable(): void
    {
        $this->assertInstanceOf(Plugin::class, new Plugin());
    }

    /**
     * @return void
     */
    public function testClassNamespace(): void
    {
        $this->assertSame('Detain\IpVlanManager', $this->reflection->getNamespaceName());
    }

    /**
     * @return void
     */
    public function testClassIsConcrete(): void
    {
        $this->assertFalse($this->reflection->isAbstract());
        $this->assertFalse($this->reflection->isInterface());
        $this->assertFalse($this->reflection->isTrait());
    }

    /**
     * @covers ::__construct
     * @return void
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(0, $constructor->getParameters());
    }

    // ---------------------------------------------------------------
    // Static properties
    // ---------------------------------------------------------------

    /**
     * @return void
     */
    public function testStaticPropertyValues(): void
    {
        $this->assertSame('IP Management', Plugin::$name);
        $this->assertSame('Enables management and allocation of IPs', Plugin::$description);
        $this->assertSame('functionality', Plugin::$type);
    }

    /**
     * @return void
     */
    public function testStaticPropertiesArePublicAndStatic(): void
    {
        foreach (['name', 'description', 'type'] as $name) {
            $this->assertTrue($this->reflection->hasProperty($name), "missing property {$name}");
            $property = $this->reflection->getProperty($name);
            $this->assertTrue($property->isStatic(), "{$name} must be static");
            $this->assertTrue($property->isPublic(), "{$name} must be public");
        }
    }

    /**
     * @return void
     */
    public function testClassDeclaresExactlyTheExpectedStaticProperties(): void
    {
        $names = array_map(
            static fn (ReflectionProperty $p): string => $p->getName(),
            array_filter(
                $this->reflection->getProperties(),
                static fn (ReflectionProperty $p): bool => $p->isStatic()
            )
        );
        sort($names);
        $this->assertSame(['description', 'name', 'type'], $names);
    }

    // ---------------------------------------------------------------
    // getHooks()
    // ---------------------------------------------------------------

    /**
     * @covers ::getHooks
     * @return void
     */
    public function testGetHooksIsStaticAndTakesNoParameters(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertCount(0, $method->getParameters());
    }

    /**
     * Test that the only hook this plugin registers is the requirements listener.
     *
     * ui.menu is commented out in the source, so getMenu() is dead at runtime. Pinning
     * the exact map keeps that visible: re-enabling the menu hook has to be a deliberate
     * edit to this test as well.
     *
     * @covers ::getHooks
     * @return void
     */
    public function testGetHooksRegistersOnlyTheRequirementsListener(): void
    {
        $this->assertSame(
            ['function.requirements' => [Plugin::class, 'getRequirements']],
            Plugin::getHooks()
        );
    }

    /**
     * Test that every hook target getHooks() names is actually callable.
     *
     * A typo in a handler name is silent until the event fires in production.
     *
     * @covers ::getHooks
     * @return void
     */
    public function testEveryHookTargetIsCallable(): void
    {
        foreach (Plugin::getHooks() as $event => $callback) {
            $this->assertIsCallable($callback, "hook {$event} points at a non-callable target");
        }
    }

    /**
     * @covers ::getHooks
     * @return void
     */
    public function testGetHooksIsIdempotent(): void
    {
        $this->assertSame(Plugin::getHooks(), Plugin::getHooks());
    }

    // ---------------------------------------------------------------
    // Event handler signatures
    // ---------------------------------------------------------------

    /**
     * @covers ::getMenu
     * @covers ::getRequirements
     * @return void
     */
    public function testEventHandlerSignatures(): void
    {
        foreach (['getMenu', 'getRequirements'] as $name) {
            $method = $this->reflection->getMethod($name);
            $this->assertTrue($method->isStatic(), "{$name} must be static");
            $this->assertTrue($method->isPublic(), "{$name} must be public");

            $params = $method->getParameters();
            $this->assertCount(1, $params, "{$name} must take exactly one parameter");
            $this->assertSame('event', $params[0]->getName());

            $type = $params[0]->getType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame(GenericEvent::class, $type->getName());
        }
    }

    /**
     * @return void
     */
    public function testClassDeclaresExactlyTheExpectedStaticMethods(): void
    {
        $names = array_map(
            static fn (ReflectionMethod $m): string => $m->getName(),
            array_filter(
                $this->reflection->getMethods(),
                static fn (ReflectionMethod $m): bool => $m->isStatic()
                    && $m->getDeclaringClass()->getName() === Plugin::class
            )
        );
        sort($names);
        $this->assertSame(['getHooks', 'getMenu', 'getRequirements'], $names);
    }

    // ---------------------------------------------------------------
    // getRequirements() behaviour
    // ---------------------------------------------------------------

    /**
     * Test that every source getRequirements() registers resolves to a file on disk.
     *
     * function_requirements() resolves a registered source as INCLUDE_ROOT.'/'.$source
     * and require_once's it, so a registration whose file is gone is a fatal on the
     * first hit of the route, not a quiet miss. Two such registrations
     * (edit_vlan_comment, vlan_port_server_manager) survived here from November 2024
     * until the preceding commit on this branch precisely because nothing ever checked.
     *
     * This is the property that matters, and it is checked by executing the real
     * handler against a recording loader rather than by reading the source text.
     *
     * @covers ::getRequirements
     * @return void
     */
    public function testEveryRegisteredRequirementSourceExistsOnDisk(): void
    {
        $loader = $this->recordingLoader();

        Plugin::getRequirements(new GenericEvent($loader));

        $packageRoot = dirname(__DIR__);
        $marker = '/vendor/detain/ip_vlan_manager';
        $missing = [];

        foreach ($loader->requirements as $requirement) {
            $inPackage = strstr($requirement['path'], $marker);
            if ($inPackage !== false) {
                // Points inside this package: resolve against the checkout so the test
                // means something standalone as well as installed under vendor/.
                $resolved = $packageRoot.substr($inPackage, strlen($marker));
            } else {
                // Anything else resolves the way function_requirements() does it.
                $resolved = dirname($packageRoot, 3).'/include/'.$requirement['path'];
            }

            if (!is_file($resolved)) {
                $missing[] = "{$requirement['name']} => {$requirement['path']} (looked for {$resolved})";
            }
        }

        $this->assertSame(
            [],
            $missing,
            'every registered requirement source must resolve to a file that exists'
        );
    }

    /**
     * Test that the existence check above is not passing vacuously.
     *
     * An empty registration table would make
     * testEveryRegisteredRequirementSourceExistsOnDisk green without ever entering its
     * loop. This asserts the loader actually saw work, through both entry points.
     *
     * @covers ::getRequirements
     * @return void
     */
    public function testGetRequirementsRegistersThroughBothEntryPoints(): void
    {
        $loader = $this->recordingLoader();

        Plugin::getRequirements(new GenericEvent($loader));

        $adminPages = array_filter(
            $loader->requirements,
            static fn (array $r): bool => $r['kind'] === 'admin_page'
        );
        $plain = array_filter(
            $loader->requirements,
            static fn (array $r): bool => $r['kind'] === 'requirement'
        );

        $this->assertCount(19, $adminPages);
        $this->assertCount(15, $plain);
        $this->assertCount(34, $loader->requirements);
    }

    /**
     * Test that no requirement is registered twice under the same name.
     *
     * The loader stores requirements in a name-keyed array, so a duplicate name silently
     * overwrites the earlier source instead of erroring.
     *
     * @covers ::getRequirements
     * @return void
     */
    public function testRegisteredRequirementNamesAreUnique(): void
    {
        $loader = $this->recordingLoader();

        Plugin::getRequirements(new GenericEvent($loader));

        $names = array_map(
            static fn (array $r): string => $r['name'],
            $loader->requirements
        );
        $duplicates = array_keys(
            array_filter(array_count_values($names), static fn (int $n): bool => $n > 1)
        );

        $this->assertSame([], $duplicates, 'requirement names must be unique');
    }

    /**
     * Test that no registration carries an empty name or source.
     *
     * Loader::add_requirement() silently drops a registration whose source is '', which
     * would leave the named function unloadable with no error anywhere.
     *
     * @covers ::getRequirements
     * @return void
     */
    public function testNoRegistrationHasAnEmptyNameOrSource(): void
    {
        $loader = $this->recordingLoader();

        Plugin::getRequirements(new GenericEvent($loader));

        foreach ($loader->requirements as $requirement) {
            $this->assertNotSame('', $requirement['name'], 'a registration has an empty function name');
            $this->assertNotSame('', $requirement['path'], "{$requirement['name']} has an empty source");
        }
    }

    // ---------------------------------------------------------------
    // Source-level checks
    // ---------------------------------------------------------------

    /**
     * @return void
     */
    public function testSourceFileHasExpectedNamespaceAndImports(): void
    {
        $filename = $this->reflection->getFileName();
        $this->assertNotFalse($filename);
        $source = file_get_contents($filename);
        $this->assertIsString($source);

        $this->assertStringStartsWith('<?php', $source);
        $this->assertStringContainsString('namespace Detain\IpVlanManager;', $source);
        $this->assertStringContainsString(
            'use Symfony\Component\EventDispatcher\GenericEvent;',
            $source
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Build a stand-in for \MyAdmin\Plugins\Loader that records what was registered.
     *
     * Both methods mirror the real signatures in
     * detain/myadmin-plugin-installer/src/Loader.php, including the optional third
     * $methods argument, so a future registration that passes request methods does not
     * blow up the stub.
     *
     * @return object
     */
    private function recordingLoader(): object
    {
        return new class () {
            /** @var array<int, array{kind: string, name: string, path: string, methods: mixed}> */
            public array $requirements = [];

            /**
             * @param string $function
             * @param string $source
             * @param mixed  $methods
             * @return void
             */
            public function add_admin_page_requirement($function, $source, $methods = false): void
            {
                $this->requirements[] = [
                    'kind' => 'admin_page',
                    'name' => $function,
                    'path' => $source,
                    'methods' => $methods,
                ];
            }

            /**
             * @param string $function
             * @param string $source
             * @param mixed  $methods
             * @return void
             */
            public function add_requirement($function, $source, $methods = false): void
            {
                $this->requirements[] = [
                    'kind' => 'requirement',
                    'name' => $function,
                    'path' => $source,
                    'methods' => $methods,
                ];
            }
        };
    }
}
