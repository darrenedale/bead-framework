<?php

declare(strict_types=1);

use Bead\Core\Application as CoreApplication;
use Bead\Core\ConsoleApplication;
use BeadTests\Framework\TestCase;

final class ConsoleApplicationTest extends TestCase
{
    private ?ConsoleApplication $m_app = null;

    private string $output = "";

    public function setUp(): void
    {}

    public function tearDown(): void
    {
        if ($this->m_app) {
            // ensure the next test that creates an app doesn't cause the c'tor to complain that an instance has
            // already been created
            $singleton = new ReflectionProperty(CoreApplication::class, "s_instance");
            $singleton->setAccessible(true);
            $singleton->setValue(null, null);
        }

        unset($this->m_app);
        parent::tearDown();
    }

    private function createApplication(string $root = __DIR__ . '/console-application-root', array $args = ["test-command.php"], Closure $configure = null, $run = null): ConsoleApplication
    {
        $this->m_app = new class($root, $args, $configure ?? function(): void {}, $run ?? fn(): int => CoreApplication::ExitOk) extends ConsoleApplication
        {
            private Closure $m_configure;

            private Closure $m_run;

            public string $output = "";

            public string $errorOutput = "";

            public function __construct(string $root, array $args, Closure $configure, Closure $run)
            {
                parent::__construct($root, $args);
                $this->m_configure = $configure->bindTo($this, $this);
                $this->m_run = $run->bindTo($this, $this);
            }

            protected function run(): int
            {
                return ($this->m_run)();
            }

            protected function configure(): void
            {
                ($this->m_configure)();
            }

            public function line(string $line): void
            {
                $this->output .= "{$line}\n";
            }

            public function errorLine(string $line): void
            {
                $this->errorOutput .= "{$line}\n";
            }
        };

        return $this->m_app;
    }

    /** Provides data for testDescription() */
    protected static function dataForTestDescription(): iterable
    {
        yield "typical" => ["A console app",];
        yield "really-lone" => [str_repeat("A really long description of a console application.", 100),];
        yield "really-short" => [".",];
    }

    /** Ensure the description of an unconfigured app is empty. */
    public function testDescription1(): void
    {
        $app = $this->createApplication(configure: fn() => throw new RuntimeException("Configure was called on the app instance."));
        self::assertEquals("", $app->description());
    }

    /**
     * Ensure the description of the app is available, once configured.
     * @param string $description
     * @dataProvider dataForTestDescription
     */
    public function testDescription2(string $description): void
    {
        $app = $this->createApplication(configure: fn() => $this->setDescription($description));
        $app->exec();
        self::assertEquals($description, $app->description());
    }

    /** Ensure the an empty description is rejected. */
    public function testDescription3(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expecting non-empty command descriptions, found \"\".");
        $app = $this->createApplication(configure: fn() => $this->setDescription(""));
        $app->exec();
    }

    /** Ensure the an wholly-whitespace description is rejected. */
    public function testDescription4(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expecting non-empty command descriptions, found \"\".");
        $app = $this->createApplication(configure: fn() => $this->setDescription(" "));
        $app->exec();
    }

    /** Ensure configure() is called by exec(). */
    public function testExec1(): void
    {
        $configured = false;

        $app = $this->createApplication(configure: function() use (&$configured): void {
            $configured = true;
        });

        $app->exec();
        self::assertTrue($configured);
    }

    /**
     *
     */
    protected static function dataForHelpTests(): iterable
    {
        yield "help-only" => [["command.php", "--help",],];
        yield "help-first" => [["command.php", "--help", "foo", "bar",],];
        yield "help-last" => [["command.php", "foo", "bar", "--help",],];
        yield "help-surrounded" => [["command.php", "foo", "--help", "bar",],];
    }

    /**
     * Ensure run() is not is called when the help arg is present.
     * @dataProvider dataForHelpTests
     */
    public function testExec2(array $args): void
    {
        $runCalled = false;

        $app = $this->createApplication(
            args: $args,
            configure: function(): void {
                $this->addArgument("foo", "The foo argument will be ignored.", optional: true);
                $this->addArgument("bar", "The bar argument will be ignored.", optional: true);
            },
            run: function() use (&$runCalled): int
            {
                $runCalled = true;
                return CoreApplication::ExitOk;
            }
        );

        $app->exec();
        self::assertFalse($runCalled);
    }

    /**
     * Ensure run() is not is called when the help arg is present.
     * @dataProvider dataForHelpTests
     */
    public function testShowHelp1(array $args): void
    {
        $app = $this->createApplication(
            args: $args,
            configure: function(): void {
                $this->addArgument("foo", "The foo argument will be ignored.", optional: true);
                $this->addArgument("bar", "The bar argument will be ignored.", optional: true);
            },
        );

        $app->exec();

        self::assertEquals(
<<<EOF
command.php: 
  -h --help  [foo] [bar]

EOF,
            $app->output
        );
    }
}
