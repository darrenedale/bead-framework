<?php

declare(strict_types=1);

use Bead\Core\Application as CoreApplication;
use Bead\Core\ConsoleApplication;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
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
            $coreApp = new StaticXRay(CoreApplication::class);
            $coreApp->s_instance = null;
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
        };

        return $this->m_app;
    }

    /** Ensure constructor defines the help flag. */
    public function testConstructor1(): void
    {
        $app = $this->createApplication();
        self::assertTrue($app->hasFlag("help"));
    }

    /** Ensure constructor defines the help flag with a short name. */
    public function testConstructor2(): void
    {
        $app = $this->createApplication();
        self::assertTrue($app->hasFlag("h"));
    }

    /** Ensure constructor defines the debug flag. */
    public function testConstructor3(): void
    {
        $app = $this->createApplication();
        self::assertTrue($app->hasFlag("debug"));
    }

    /** Ensure constructor sets the script name. */
    public function testConstructor4(): void
    {
        $app = $this->createApplication(args:["command.php",]);
        self::assertEquals("command.php", $app->executedScript());
    }

    /** Ensure constructor keeps the full path of the script. */
    public function testConstructor5(): void
    {
        $app = $this->createApplication(args:["/path/to/command.php",]);
        self::assertEquals("/path/to/command.php", $app->executedScript());
    }

    /** Ensure constructor sets an empty string as the script when none is provided. */
    public function testConstructor6(): void
    {
        $app = $this->createApplication(args:[]);
        self::assertSame("", $app->executedScript());
    }

    protected static function validParameterNames(): iterable
    {
        yield "typical1" => ["foo"];
        yield "typical2" => ["bar"];
        yield "typical3" => ["ex"];
        yield "typical-upper-1" => ["FOO1"];
        yield "typical-upper-2" => ["BAR2"];
        yield "typical-upper-3" => ["FooBar12"];
        yield "includes-numeric" => ["life42"];
        yield "includes-hyphen" => ["life-42"];
        yield "includes-underscore" => ["life_42"];
        yield "includes-underscore-and-hyphen" => ["life_-42"];
        yield "extreme-numeric" => ["a1"];
        yield "extreme-hyphen" => ["a-"];
        yield "extreme-underscore" => ["a_"];
        yield "extreme-long" => [str_repeat("foobarbaz-_42-", 100)];
    }

    protected static function invalidParameterNames(): iterable
    {
        yield "empty" => [""];
        yield "single-whitespace" => [" "];
        yield "more-whitespace" => ["   "];
        yield "single-hyphen" => ["-"];
        yield "more-hyphen" => ["---"];
        yield "single-underscore" => ["_"];
        yield "more-underscore" => ["___"];
        yield "all-invalid" => ["4-2_"];
        yield "leading-whitespace" => [" foo"];
        yield "trailing-whitespace" => ["foo "];
        yield "inline-whitespace" => ["foo bar"];
        yield "digit" => ["4"];
        yield "numeric" => ["42"];
        yield "leading-numeric" => ["42-life"];
        yield "leading-hyphen" => ["-life42"];
        yield "leading-underscore" => ["_life42"];
    }

    protected static function validShortParameterNames(): iterable
    {
        foreach (range("a", "z") as $ch) {
            yield $ch => [$ch];
        }

        foreach (range("A", "Z") as $ch) {
            yield $ch => [$ch];
        }
    }

    protected static function invalidShortParameterNames(): iterable
    {
        yield "empty" => [""];
        yield "whitespace" => [" "];
        yield "punctuation" => ["-"];
        yield "leading-whitespace" => [" f"];
        yield "trailing-whitespace" => ["f "];
        yield "surrounding-whitespace" => [" f "];
        yield "digit" => ["4"];
        yield "too-long" => ["ab"];
    }

    /**
     * Ensure isValidParameterName() passes valid parameter names.
     * @dataProvider validParameterNames
     */
    public function testIsValidParameterName1(string $name): void
    {
        $actual = (new StaticXRay(ConsoleApplication::class))->isValidParameterName($name);
        self::assertTrue($actual);
    }

    /**
     * Ensure isValidParameterName() rejects invalid parameter names.
     * @dataProvider invalidParameterNames
     */
    public function testIsValidParameterName2(string $name): void
    {
        $actual = (new StaticXRay(ConsoleApplication::class))->isValidParameterName($name);
        self::assertFalse($actual);
    }

    /**
     * Ensure isValidShortParameterName() passes valid parameter names.
     * @dataProvider validShortParameterNames
     */
    public function testIsValidShortParameterName1(string $name): void
    {
        $actual = (new StaticXRay(ConsoleApplication::class))->isValidShortParameterName($name);
        self::assertTrue($actual);
    }

    /**
     * Ensure isValidShortParameterName() rejects invalid parameter names.
     * @dataProvider invalidShortParameterNames
     */
    public function testIsValidShortParameterName2(string $name): void
    {
        $actual = (new StaticXRay(ConsoleApplication::class))->isValidShortParameterName($name);
        self::assertFalse($actual);
    }

    /** Ensure the description of an unconfigured app is empty. */
    public function testDescription1(): void
    {
        $app = $this->createApplication(configure: fn() => throw new RuntimeException("Configure was called on the app instance."));
        self::assertEquals("", $app->description());
    }

    protected static function dataForTestDescription2(): iterable
    {
        yield "typical" => ["A console app", "A console app",];
        yield "really-lone" => [
            str_repeat("A really long description of a console application.", 100),
            str_repeat("A really long description of a console application.", 100),
        ];
        yield "really-short" => [".", ".",];
        yield "leading-whitespace-trimmed" => ["  Description", "Description",];
        yield "trailing-whitespace-trimmed" => ["The description  ", "The description",];
        yield "surrounding-whitespace-trimmed" => ["   A description. ", "A description.",];
    }

    /**
     * Ensure the description can be set and is trimmed.
     * @param string $description
     * @dataProvider dataForTestDescription2
     */
    public function testDescription2(string $description, string $expected): void
    {
        $app = new XRay($this->createApplication());
        $app->setDescription($description);
        self::assertEquals($expected, $app->description());
    }

    /**
     * Ensure the description of the app is available, once configured.
     * @param string $description
     * @dataProvider dataForTestDescription2
     */
    public function testDescription3(string $description): void
    {
        $app = new XRay($this->createApplication(configure: fn() => $this->setDescription($description)));
        self::assertEquals("", $app->description());
        $app->configure();
        self::assertNotEquals("", $app->description());
    }

    protected static function dataForTestDescription4(): iterable
    {
        yield "empty" => ["",];
        yield "single-whitespace" => [" ",];
        yield "extra-whitespace" => ["  ",];
    }

    /**
     * Ensure the description can't be set to nothing.
     * @dataProvider dataForTestDescription4
     */
    public function testDescription4(string $description): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expecting non-empty command description, found \"{$description}\"");
        $app = new XRay($this->createApplication());
        $app->setDescription($description);
    }

    /** Ensure descriptions are trimmed. */
    public function testDescription5(): void
    {
        $app = new XRay($this->createApplication());
        $app->setDescription("  A command description ");
        self::assertEquals("A command description", $app->description());
    }

    /** Ensure configure() is covered. */
    public function testConfigure1(): void
    {
        $app = new XRay(new class extends ConsoleApplication {
            public function __construct()
            {}

            public function run(): int
            {}
        });

        // this method is empty, this test is just for coverage completeness
        $app->configure();
        self::markTestAsExternallyVerified();
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
     * Provides command-line arguments for testing the command help.
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

        $stream = fopen("php://memory", "w");
        $app->setOutStream($stream);
        $app->exec();
        self::assertFalse($runCalled);
    }

    /** Ensure exec() parses command-line args */
    public function testExec3(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--bead", "framework",],
            configure: function(): void {
                $this->addOption("bead", "b", "An argument to parse.", self::TypeString, false, "framework");
            },
        ));

        self::assertFalse($app->nameIsDefined("bead"));
        self::assertFalse($app->shortNameIsDefined("b"));
        $app->exec();
        $option = $app->optionDefinition("bead");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertEquals((new ReflectionClassConstant(ConsoleApplication::class, "Option"))->getValue(), $option->type);
        self::assertEquals("bead", $option->name);
        self::assertEquals("b", $option->shortName);
    }

    /**
     * Ensure run() is not is called when the help arg is present.
     * @dataProvider dataForHelpTests
     */
    public function testShowHelp1(array $args): void
    {
        $stream = fopen("php://memory", "w+");
        $app = $this->createApplication(
            args: $args,
            configure: function(): void {
                $this->setDescription("Test command");
                $this->addArgument("foo", "The foo argument will be ignored.", optional: true);
                $this->addArgument("bar", "The bar argument will be ignored.", optional: true);
            },
        );

        $app->setOutStream($stream);
        $app->exec();
        fseek($stream, 0, SEEK_SET);

        self::assertEquals(
<<<EOF
command.php: Test command
  [-h] [--help] [--debug] [foo] [bar]

Flags
    --help|-h Show the command's help message.
    --debug Run the command in debug mode.

Arguments
    foo (any, optional) The foo argument will be ignored.
    bar (any, optional) The bar argument will be ignored.

EOF,
            fread($stream, 1024),
        );
    }


    /** Ensure args, options and flags are present in the help. */
    public function testShowHelp2(): void
    {
        $stream = fopen("php://memory", "w+");
        $app = new XRay($this->createApplication(
            args: ["test-command.php", "--bead", "framework", "foo-value",],
            configure: function(): void {
                $this->setDescription("Test command.");
                $this->addFlag("test", description: "A test flag.");
                $this->addFlag("another-test", "a", "Another test flag.", default: true);
                $this->addArgument("foo", "The foo argument will be ignored.", type: self::TypeInt, optional: false);
                $this->addArgument("bar", "The bar argument will be ignored.", optional: true, default: "baz");
                $this->addOption("bead", description: "The bead option will be ignored.", type: self::TypeString, optional: false);
                $this->addOption("framework", "f", "The framework option will be ignored.", type: self::TypeArray, optional: true, default: "bead");
            },
        ));

        $app->setOutStream($stream);
        $app->configure();
        $app->showHelp();
        fseek($stream, 0, SEEK_SET);

        self::assertEquals(
<<<EOF
test-command.php: Test command.
  [-ha] [--help] [--debug] [--test] [--another-test] --bead [--framework|-f...=bead] foo [bar]

Flags
    --help|-h Show the command's help message.
    --debug Run the command in debug mode.
    --test A test flag.
    --another-test|-a Another test flag. (Default is on.)

Options
    --bead <string> The bead option will be ignored.
    --framework|-f <any> (optional) The framework option will be ignored. (Can be specified more than once.) (Default is bead.)

Arguments
    foo (integer) The foo argument will be ignored.
    bar (any, optional) The bar argument will be ignored. (Default is baz.)

EOF,
            fread($stream, 1024),
        );
    }

    /** Ensure --debug command-line flag puts app into debug mode. */
    public function testIsInDebugMode1(): void
    {
        $app = new XRay($this->createApplication(args: ["command.php", "--debug",]));
        // command-line flag not in play until exec() is called
        self::assertFalse($app->isInDebugMode());
        $app->configure();
        $app->parseCommandLineArguments();
        self::assertTrue($app->isInDebugMode());
    }

    protected static function validFlagAndOptionArguments(): iterable
    {
        yield "typical" => ["--flag", "flag"];
        yield "typical-short" => ["-f", "f"];
        yield "typical-hyphenated" => ["--option-name", "option-name"];
        yield "extreme-extra-leading-hyphens" => ["---the-option", "-the-option"];
    }

    /**
     * Ensure extractName() successfully extracts from long and short name options and flags
     * @dataProvider validFlagAndOptionArguments
     */
    public function testExtractName1(string $arg, string $expected): void
    {
        $app = new StaticXRay(ConsoleApplication::class);
        self::assertEquals($expected, $app->extractName($arg));
    }

    protected static function invalidFlagAndOptionArguments(): iterable
    {
        yield "empty" => [""];
        yield "whitespace" => ["   "];
        yield "no-leading-hyphens" => ["option-name",];
        yield "whitespace-before-leading-hyphens" => [" --the-option",];
    }

    /**
     * Ensure extractName() throws when not given an option or flag name
     * @dataProvider invalidFlagAndOptionArguments
     */
    public function testExtractName2(string $arg): void
    {
        $app = new StaticXRay(ConsoleApplication::class);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected option or flag, found \"{$arg}\"");
        $app->extractName($arg);
    }

    protected static function validCondensedFlagArguments(): iterable
    {
        yield "single-flag" => [["-f",], ["-f",],];
        yield "multiple-flags" => [["-bf",], ["-b", "-f",],];
        yield "multiple-flags-and-other-args" => [["--option", "-bf", "argument"], ["--option", "-b", "-f", "argument",],];
    }

    /** Ensure we can successfully determine a name is defined. */
    public function testNameIsDefined1(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--framework", "bead", "input-value"],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        // always defined
        self::assertTrue($app->nameIsDefined("help"));
        self::assertTrue($app->nameIsDefined("debug"));

        $app->configure();
        $app->parseCommandLineArguments();

        // what we've configured
        self::assertTrue($app->nameIsDefined("bead"));
        self::assertTrue($app->nameIsDefined("not-bead"));
        self::assertTrue($app->nameIsDefined("framework"));
        self::assertTrue($app->nameIsDefined("input"));
    }

    /** Ensure we can successfully determine a name is not defined. */
    public function testNameIsDefined2(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--framework", "bead", "input-value"],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();

        // names with whitespace don't match
        self::assertFalse($app->nameIsDefined(" help"));
        self::assertFalse($app->nameIsDefined("help "));
        self::assertFalse($app->nameIsDefined(" bead"));
        self::assertFalse($app->nameIsDefined("bead "));

        // matches are case-sensitive
        self::assertFalse($app->nameIsDefined(" Help"));
        self::assertFalse($app->nameIsDefined("Help "));
        self::assertFalse($app->nameIsDefined(" Bead"));
        self::assertFalse($app->nameIsDefined("Bead "));

        // things we haven't configured don't match
        self::assertFalse($app->nameIsDefined("bead-framework"));
    }

    /** Ensure we can successfully determine a short name is defined. */
    public function testShortNameIsDefined1(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--framework", "bead", "input-value"],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        // always defined
        self::assertTrue($app->shortNameIsDefined("h"));

        $app->configure();
        $app->parseCommandLineArguments();

        // what we've configured
        self::assertTrue($app->shortNameIsDefined("b"));
        self::assertTrue($app->shortNameIsDefined("B"));
        self::assertTrue($app->shortNameIsDefined("f"));
    }

    /** Ensure we can successfully determine a name is not defined. */
    public function testShortNameIsDefined2(): void
    {
        // NOTE option and flag don't have short names
        $app = new XRay($this->createApplication(
            args: ["command.php", "--framework", "bead", "input-value"],
            configure: function(): void {
                $this->addFlag("bead", description: "The bead flag");
                $this->addOption("framework", description: "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();

        self::assertFalse($app->shortNameIsDefined("b"));
        self::assertFalse($app->shortNameIsDefined("f"));
        self::assertFalse($app->shortNameIsDefined("i"));

        // things we haven't configured don't match
        self::assertFalse($app->shortNameIsDefined("x"));
    }

    protected static function dataForTestShortNameIsDefined3(): iterable
    {
        yield "empty" => [""];
        yield "leading-whitespace" => [" h"];
        yield "trailing-whitespace" => ["h "];
        yield "surrounding-whitespace" =>[" h "];
        yield "regular-name" =>["help"];
    }

    /**
     * Ensure we get a logic exception if the programmer has provided an invalid short name.
     * @dataProvider dataForTestShortNameIsDefined3
     */
    public function testShortNameIsDefined3(string $shortName): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Invalid short name \"{$shortName}\" provided to shortNameIsDefined() helper.");
        $app->shortNameIsDefined($shortName);
    }

    protected static function dataForTestIsCompressedFlags1(): iterable
    {
        yield "single-flag" => ["-a", false,];
        yield "two-flags" => ["-ac", true,];
        yield "long-name" => ["--flag", false,];
        yield "argument" => ["flag", false,];
        yield "many-flags" => ["-flagtext", true,];
        yield "hyphen-only" => ["-", false,];
        yield "contains-digits" => ["-abc1def", false,];
        yield "empty" => ["", false,];
        yield "whitespace" => [" ", false,];
        yield "leading-whitespace" => [" -abc", false,];
        yield "trailing-whitespace" => ["-abc ", false,];
        yield "surrounding-whitespace" => [" -abc ", false,];
        yield "multiple-whitespace" => ["   ", false,];
    }

    /**
     * Ensure compressed flags are expanded correctly.
     *
     * @dataProvider dataForTestIsCompressedFlags1
     * @param string $compressed The string of compressed flags, with the leading "-".
     * @param bool $expected Whether the string represents a valid set of compressed flags.
     */
    public function testIsCompressedFlags1(string $arg, bool $expected): void
    {
        $consoleApplication = new StaticXRay(ConsoleApplication::class);
        self::assertEquals($expected, $consoleApplication->isCompressedFlags($arg));
    }

    protected static function dataForTestExpandCompressedFlags1(): iterable
    {
        yield "single-flag" => ["-a", ["-a",],];
        yield "two-flags" => ["-ac", ["-a", "-c",],];
        yield "many-flags" => ["-acfbhnjCKgm", ["-a", "-c", "-f", "-b", "-h", "-n", "-j", "-C", "-K", "-g", "-m",],];
    }

    /**
     * Ensure compressed flags are expanded correctly.
     *
     * @dataProvider dataForTestExpandCompressedFlags1
     * @param string $compressed The string of compressed flags, with the leading "-".
     * @param array $expected The expected array of uncompressed flags.
     */
    public function testExpandCompressedFlags1(string $compressed, array $expected): void
    {
        $consoleApplication = new StaticXRay(ConsoleApplication::class);
        self::assertEquals($expected, $consoleApplication->expandCompressedFlags($compressed));
    }

    protected static function dataForTestParseCommandLineArguments1(): iterable
    {
        yield "all-long-flag-option-arg" => [["--bead", "--framework", "bead", "input-value",], true,];
        yield "all-long-flag-arg-option" => [["--bead", "input-value", "--framework", "bead",], true,];
        yield "all-long-option-flag-arg" => [["--framework", "bead", "--bead", "input-value",], true,];
        yield "all-long-option-arg-flag" => [["--framework", "bead", "input-value", "--bead",], true,];
        yield "all-long-arg-flag-option" => [["input-value", "--bead", "--framework", "bead",], true,];
        yield "all-long-arg-option-flag" => [["input-value", "--framework", "bead", "--bead",], true,];
    }

    /**
     * Ensure we can successfully parse valid command-line arguments.
     * @dataProvider dataForTestParseCommandLineArguments1
     */
    public function testParseCommandLineArguments1(array $args, bool $expectedFlagValue): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", ...$args,],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();
        self::assertEquals("bead", $app->optionValue("framework"));
        self::assertSame($expectedFlagValue, $app->flagValue("bead"));
        self::assertEquals("input-value", $app->argumentValue("input"));
    }

    protected static function dataForTestParseCommandLineArguments2(): iterable
    {
        yield "duplicate-flag" => [["--bead", "--bead",], "--bead",];
        yield "duplicate-option" => [["--framework", "bead", "--framework", "another-bead",], "--framework",];
        yield "duplicate-option-amongst-others" => [["--framework", "bead", "--bead", "--framework", "another-bead", "input-value",], "--framework",];
        yield "duplicate-field-amongst-others" => [["--framework", "bead", "--bead", "input-value", "--bead",], "--bead",];
    }

    /**
     * Ensure we reject duplicate command-line arguments during parsing.
     * @dataProvider dataForTestParseCommandLineArguments2
     */
    public function testParseCommandLineArguments2(array $args, string $duplicateParameterName): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", ...$args,],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Command-line parameter {$duplicateParameterName} was given more than once, but it doesn't accept multiple values");
        $app->parseCommandLineArguments();
    }

    /** Ensure we reject options in command-line arguments that aren't given with values. */
    public function testParseCommandLineArguments3(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--bead", "input-value", "--framework",],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Command-line parameter --framework expects a value but none was given");
        $app->parseCommandLineArguments();
    }

    /** Ensure we reject command-line arguments that aren't defined. */
    public function testParseCommandLineArguments4(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--bead", "input-value", "--framework", "bead", "--undefined",],
            configure: function(): void {
                $this->addFlag("bead", "b", "The bead flag");
                $this->addOption("framework", "f", "The framework option");
                $this->addArgument("input", "The input argument");
            }
        ));

        $app->configure();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Command-line argument --undefined is not recognised");
        $app->parseCommandLineArguments();
    }

    /** Ensure compressed flags are expanded. */
    public function testParseCommandLineArguments5(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "-abc",],
            configure: function(): void {
                $this->addFlag("another", "a", "Another flag");
                $this->addFlag("bead", "b", "The bead flag");
                $this->addFlag("configuration", "c", "The configuration flag");
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();
        self::assertTrue($app->flagValue("another"));
        self::assertTrue($app->flagValue("bead"));
        self::assertTrue($app->flagValue("configuration"));
    }

    protected static function dataForTestValildateArguments1(): iterable
    {
        yield "int-42" => [ConsoleApplication::TypeInt, "42", 42,];
        yield "int-0" => [ConsoleApplication::TypeInt, "0", 0,];
        yield "int-minus-3" => [ConsoleApplication::TypeInt, "-3", -3,];
        yield "int-plus-42" => [ConsoleApplication::TypeInt, "+42", 42,];
        yield "float-3.14" => [ConsoleApplication::TypeFloat, "3.14", 3.14,];
        yield "float-0.0" => [ConsoleApplication::TypeFloat, "0.0", 0.0,];
        yield "float-minus-7.853" => [ConsoleApplication::TypeFloat, "-7.853", -7.853,];
        yield "float-plus-3.14" => [ConsoleApplication::TypeFloat, "+3.14", 3.14,];
        yield "string-empty" => [ConsoleApplication::TypeString, "", "",];
        yield "string-whitespace" => [ConsoleApplication::TypeString, "   ", "   ",];
        yield "string-bead framework" => [ConsoleApplication::TypeString, "bead framework", "bead framework",];
        yield "string-leading-whitespace" => [ConsoleApplication::TypeString, " bead", " bead",];
        yield "string-trailing-whitespace" => [ConsoleApplication::TypeString, "framework ", "framework ",];
        yield "string-surrounding-whitespace" => [ConsoleApplication::TypeString, " bead-framework ", " bead-framework ",];
        yield "any-empty-string" => [ConsoleApplication::TypeAny, "", "",];
        yield "any-whitespace-string" => [ConsoleApplication::TypeAny, "   ", "   ",];
        yield "any-bead framework-string" => [ConsoleApplication::TypeAny, "bead framework", "bead framework",];
        yield "any-leading-whitespace-string" => [ConsoleApplication::TypeAny, " bead", " bead",];
        yield "any-trailing-whitespace-string" => [ConsoleApplication::TypeAny, "framework ", "framework ",];
        yield "any-surrounding-whitespace-string" => [ConsoleApplication::TypeAny, " bead-framework ", " bead-framework ",];
        yield "any-int-42" => [ConsoleApplication::TypeAny, "42", "42",];
        yield "any-int-0" => [ConsoleApplication::TypeAny, "0", "0",];
        yield "any-int-minus-3" => [ConsoleApplication::TypeAny, "-3", "-3",];
        yield "any-int-plus-42" => [ConsoleApplication::TypeAny, "+42", "+42",];
        yield "any-float-3.14" => [ConsoleApplication::TypeAny, "3.14", "3.14",];
        yield "any-float-0.0" => [ConsoleApplication::TypeAny, "0.0", "0.0",];
        yield "any-float-minus-7.853" => [ConsoleApplication::TypeAny, "-7.853", "-7.853",];
        yield "any-float-plus-3.14" => [ConsoleApplication::TypeAny, "+3.14", "+3.14",];
    }

    /**
     * Ensure we successfully validate all types of command-line arguments, options and flags.
     * @dataProvider dataForTestValildateArguments1
     */
    public function testValidateArguments1(int $type, string $arg, mixed $expectedValue): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--test-option", $arg,],
            configure: function() use ($type): void {
                $this->addOption("test-option", description: "The framework option", type: $type);
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();
        $app->validateCommandLineArguments();
        self::assertSame($expectedValue, $app->optionValue("test-option"));
    }

    /** Ensure we can parse and validate array args successfully */
    public function testValidateArguments2(): void
    {
        $app = new XRay($this->createApplication(
            args: ["command.php", "--test-option", "value-1", "--bead", "--test-option", "value-2",],
            configure: function(): void {
                $this->addFlag("bead", "b",  description: "The bead flag");
                $this->addOption("test-option", description: "The framework option", type: ConsoleApplication::TypeArray);
            }
        ));

        $app->configure();
        $app->parseCommandLineArguments();
        $app->validateCommandLineArguments();
        self::assertSame(["value-1", "value-2",], $app->optionValue("test-option"));
    }

    /** TODO Ensure we reject values that are not valid for all types of command-line arguments and options. */

    /** Ensure we can add an option without a short name. */
    public function testAddOption1(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("option-name"));
        self::assertFalse($app->shortNameIsDefined("o"));
        $app->addOption("option-name", null, "Test option");
        self::assertTrue($app->hasOption("option-name"));
        self::assertFalse($app->shortNameIsDefined("o"));
    }

    /** Ensure we can add an option with a short name. */
    public function testAddOption2(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("option-name"));
        self::assertFalse($app->shortNameIsDefined("o"));
        $app->addOption("option-name", "o", "Test option");
        self::assertTrue($app->hasOption("option-name"));
        self::assertTrue($app->shortNameIsDefined("o"));
    }

    protected static function emptyParameterDescriptions(): iterable
    {
        yield "empty" => [""];
        yield "single-whitespace" => [" "];
        yield "multiple-whitespace" => ["   "];
    }

    protected static function validParameterTypes(): iterable
    {
        yield "any" => [ConsoleApplication::TypeAny];
        yield "string" => [ConsoleApplication::TypeString];
        yield "int" => [ConsoleApplication::TypeInt];
        yield "float" => [ConsoleApplication::TypeFloat];
        yield "array" => [ConsoleApplication::TypeArray];
    }

    /**
     * Ensure empty descriptions are rejected.
     * @dataProvider emptyParameterDescriptions
     */
    public function testAddOption3(string $description): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected non-empty option description, found \"{$description}\"");
        $app->addOption("test-option", description: $description);
    }

    /**
     * Ensure we can add options of all types.
     * @dataProvider validParameterTypes
     */
    public function testAddOption4(int $type): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option", type: $type);
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertEquals($type, $option->dataType);
    }

    /** Ensure the default type is Any when adding an option. */
    public function testAddOption5(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option");
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertEquals(ConsoleApplication::TypeAny, $option->dataType);
    }

    /** Ensure we can add a mandatory option. */
    public function testAddOption6(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option", optional: false);
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertFalse($option->optional);
    }

    /** Ensure we can add an optional option. */
    public function testAddOption7(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option", optional: true);
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertTrue($option->optional);
    }

    /** Ensure options are mandatory by default. */
    public function testAddOption8(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option");
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertFalse($option->optional);
    }

    /** Ensure we can add an option with a default. */
    public function testAddOption9(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option", default: "test-option-value-1");
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertEquals("test-option-value-1", $option->default);
    }

    /** Ensure we can add an option without a default. */
    public function testAddOption10(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option");
        $option = $app->optionDefinition("test-option");
        self::assertInstanceOf(StdClass::class, $option);
        self::assertNull($option->default);
    }

    protected static function invalidPrameterDataTypes(): iterable
    {
        yield "first-lower" => [-1,];
        yield "first-higher" => [5,];
        yield "negative" => [-99,];
        yield "positive" => [99,];
    }

    /**
     * Ensure addOption() rejects invalid data types.
     * @dataProvider invalidPrameterDataTypes
     */
    public function testAddOption11(int $type): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid data type, found \"{$type}\"");
        $app->addOption("test-option", description: "Test option", type: $type);
    }

    /**
     * Ensure addOption() rejects invalid names.
     * @dataProvider invalidParameterNames
     */
    public function testAddOption12(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid option name, found \"{$name}\"");
        $app->addOption($name, description: "Test option");
    }

    /**
     * Ensure addOption() rejects invalid parameter short names.
     * @dataProvider invalidShortParameterNames
     */
    public function testAddOption13(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid option short name, found \"{$name}\"");
        $app->addOption("test-option", shortName: $name, description: "Test option");
    }

    /** Ensure we can't re-define help as name of option. */
    public function testAddOption14(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option name \"help\" is already defined");
        $app->addOption("help", description: "Test option");
    }

    /** Ensure we can't re-define h as short name of option. */
    public function testAddOption15(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option short name \"h\" is already defined");
        $app->addOption("test-option", shortName: "h", description: "Test option");
    }

    /** Ensure we can't re-define debug as name of option. */
    public function testAddOption16(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option name \"debug\" is already defined");
        $app->addOption("debug", description: "Test option");
    }

    /** Ensure addOption() rejects names that are already in use. */
    public function testAddOption17(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", description: "Test option");
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option name \"test-option\" is already defined");
        $app->addOption("test-option", description: "Test option");
    }

    /** Ensure addOption() rejects short names that are already in use. */
    public function testAddOption18(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test-option", "o", description: "Test option");
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option short name \"o\" is already defined");
        $app->addOption("another-test-option", "o", description: "Another test option");
    }

    /**
     * Ensure we can add a valid argument.
     * @dataProvider validParameterNames
     */
    public function testAddArgument1(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined($name));
        $app->addArgument($name, "Test argument");
        self::assertTrue($app->hasArgument($name));
    }

    /**
     * Ensure we can add arguments of all types.
     * @dataProvider validParameterTypes
     */
    public function testAddArgument2(int $type): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument", $type);
        self::assertTrue($app->hasArgument("test-argument"));
    }

    /**
     * Ensure the default type is Any when adding an argument.
     * @dataProvider validParameterTypes
     */
    public function testAddArgument3(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument");
        $arg = $app->argumentDefinition("test-argument");
        self::assertEquals(ConsoleApplication::TypeAny, $arg->dataType);
    }

    /** Ensure we can add a mandatory argument. */
    public function testAddArgument4(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument", optional: false);
        $arg = $app->argumentDefinition("test-argument");
        self::assertFalse($arg->optional);
    }

    /** Ensure we can add an optional argument. */
    public function testAddArgument5(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument", optional: true);
        $arg = $app->argumentDefinition("test-argument");
        self::assertTrue($arg->optional);
    }

    /** Ensure arguments are mandatory by default. */
    public function testAddArgument6(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument");
        $arg = $app->argumentDefinition("test-argument");
        self::assertFalse($arg->optional);
    }

    /** Ensure we can add an argument with a default. */
    public function testAddArgument7(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument", default: "the value");
        $arg = $app->argumentDefinition("test-argument");
        self::assertEquals("the value", $arg->default);
    }

    /** Ensure we can add an argument without a default. */
    public function testAddArgument8(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument", default: null);
        $arg = $app->argumentDefinition("test-argument");
        self::assertNull($arg->default);
    }

    /** Ensure by default arguments don't have a default. */
    public function testAddArgument9(): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined("test-argument"));
        $app->addArgument("test-argument", "Test argument");
        $arg = $app->argumentDefinition("test-argument");
        self::assertNull($arg->default);
    }

    /**
     * Ensure empty descriptions are rejected.
     * @dataProvider emptyParameterDescriptions
     */
    public function testAddArgument10(string $description): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected non-empty argument description, found \"{$description}\"");
        $app->addArgument("test-argument", $description);
    }

    /**
     * Ensure addArgument() rejects invalid data types.
     * @dataProvider invalidPrameterDataTypes
     */
    public function testAddArgument11(int $type): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid data type, found \"{$type}\"");
        $app->addArgument("test-argument", "Test argument", type: $type);
    }

    /**
     * Ensure addArgument() rejects invalid parameter names.
     * @dataProvider invalidParameterNames
     */
    public function testAddArgument12(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid argument name, found \"{$name}\"");
        $app->addArgument($name, "Test argument");
    }

    /** Ensure addArgument() rejects names that are already in use. */
    public function testAddArgument13(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test-argument", "Test argument");
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Argument name \"test-argument\" is already defined");
        $app->addArgument("test-argument", "Test argument");
    }

    /** Ensure we can't re-define help as name of arg. */
    public function testAddArgument14(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Argument name \"help\" is already defined");
        $app->addArgument("help", "Defining help again");
    }

    /** Ensure we can't re-define debug as name of arg. */
    public function testAddArgument15(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Argument name \"debug\" is already defined");
        $app->addArgument("debug", "Defining debug again");
    }

    /** Ensure addArgument() rejects mandatory arguments after the first optional one. */
    public function testAddArgument16(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("optional-arg", "An optional arg", optional: true);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Mandatory arguments cannot be defined after optional arguments");
        $app->addArgument("mandatory-arg", "A mandatory arg", optional: false);
    }

    /**
     * Ensure we can add a valid flag.
     * @dataProvider validParameterNames
     */
    public function testAddFlag1(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::assertFalse($app->nameIsDefined($name));
        $app->addFlag($name, description: "Test flag");
        self::assertTrue($app->hasFlag($name));
    }

    /** Ensure we can add a negatable flag. */
    public function testAddFlag2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "t", "Test flag", negatable: true);
        $flag = $app->flagDefinition("test-flag");
        self::assertEquals("not-test-flag", $flag->negatedName);
        self::assertEquals("T", $flag->negatedShortName);
    }

    /** Ensure we can add a non-negatable flag. */
    public function testAddFlag3(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "t", "Test flag", negatable: false);
        $flag = $app->flagDefinition("test-flag");
        self::assertNull($flag->negatedName);
        self::assertNull($flag->negatedShortName);
    }

    /** Ensure flags are negatable by default. */
    public function testAddFlag4(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "t", "Test flag");
        $flag = $app->flagDefinition("test-flag");
        self::assertEquals("not-test-flag", $flag->negatedName);
        self::assertEquals("T", $flag->negatedShortName);
    }

    /** Ensure we reject negatable flags whose negated name is alredy defined. */
    public function testAddFlag5(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("not-test-flag", description: "Existing test flag", negatable: false);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Negated flag name \"not-test-flag\" is already defined");
        $app->addFlag("test-flag", description: "Test flag", negatable: true);
    }

    /** Ensure we reject negatable flags whose negated short name is alredy defined. */
    public function testAddFlag6(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("existing-test-flag", "T", "Existing test flag", negatable: false);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Negated flag short name \"T\" is already defined");
        $app->addFlag("test-flag", "t", description: "Test flag", negatable: true);
    }

    /** Ensure we negatable flags whose short name is already upper-case don't get a negated short name. */
    public function testAddFlag7(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "T", "Test flag", negatable: true);
        $flag = $app->flagDefinition("test-flag");
        self::assertEquals("not-test-flag", $flag->negatedName);
        self::assertNull($flag->negatedShortName);
    }

    /** Ensure we can add a flag with a default. */
    public function testAddFlag8(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "t", "Test flag", default: true);
        $flag = $app->flagDefinition("test-flag");
        self::assertTrue($flag->default);
    }

    /**
     * Ensure we can add a flag without a default.
     *
     * In this scenario the flag is off, i.e. default is false.
     */
    public function testAddFlag9(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", "t", "Test flag");
        $flag = $app->flagDefinition("test-flag");
        self::assertFalse($flag->default);
    }

    /**
     * Ensure empty descriptions are rejected.
     * @dataProvider emptyParameterDescriptions
     */
    public function testAddFlag10(string $description): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected non-empty flag description, found \"{$description}\"");
        $app->addFlag("test-flag", "t", $description);
    }

    /**
     * Ensure addFlag() rejects invalid parameter names.
     * @dataProvider invalidParameterNames
     */
    public function testAddFlag11(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid flag name, found \"{$name}\"");
        $app->addFlag($name, description: "Test flag");
    }

    /**
     * Ensure addFlag() rejects invalid parameter short names.
     * @dataProvider invalidShortParameterNames
     */
    public function testAddFlag12(string $name): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Expected valid flag short name, found \"{$name}\"");
        $app->addFlag("test-flag", $name, "Test flag");
    }

    /** Ensure addFlag() rejects names that are already in use. */
    public function testAddFlag13(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test-flag", description: "Existing test flag");
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag name \"test-flag\" is already defined");
        $app->addFlag("test-flag", description: "Test flag");
    }

    /** Ensure addFlag() rejects short names that are already in use. */
    public function testAddFlag14(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("existing-test-flag", "t", "Existing test flag");
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag short name \"t\" is already defined");
        $app->addFlag("test-flag", "t", "Test flag");
    }

    /** Ensure we can't re-define help as name of flag. */
    public function testAddFlag15(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag name \"help\" is already defined");
        $app->addFlag("help", description: "Redefined help flag");
    }

    /** Ensure we can't re-define h as short name of flag. */
    public function testAddFlag16(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag short name \"h\" is already defined");
        $app->addFlag("more-help", "h", "Redefined help flag");
    }

    /** Ensure we can't re-define debug as name of flag. */
    public function testAddFlag17(): void
    {
        $app = new XRay($this->createApplication());
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag name \"debug\" is already defined");
        $app->addFlag("debug", description: "Redefined debug flag");
    }

    /** TODO Ensure flagDefinition() returns the correct definition. */
    /** TODO Ensure flagDefinition() returns null for undefined flags. */
    /** TODO Ensure flagDefinition() returns null for undefined flags when an option with the matching name exists. */
    /** TODO Ensure flagDefinition() returns null for undefined flags when an argument with the matching name exists. */

    /** TODO Ensure optionDefinition() returns the correct definition. */
    /** TODO Ensure optionDefinition() returns null for undefined options. */
    /** TODO Ensure optionDefinition() returns null for undefined options when a flag with the matching name exists. */
    /** TODO Ensure optionDefinition() returns null for undefined options when an argument with the matching name exists. */

    /** TODO Ensure argumentDefinition() returns the correct definition. */
    /** TODO Ensure argumentDefinition() returns null for undefined arguments. */
    /** TODO Ensure argumentDefinition() returns null for undefined arguments when an option with the matching name exists. */
    /** TODO Ensure argumentDefinition() returns null for undefined arguments when a flag with the matching name exists. */

    /** Ensure write() writes to the expected stream. */
    public function testWrite1(): void
    {
        $stream = fopen("php://memory", "w+");
        $app = new Xray($this->createApplication());
        $app->write("test content", $stream);
        fseek($stream, 0, SEEK_SET);
        self::assertEquals("test content", fgets($stream));
        fclose($stream);
    }

    /** Ensure line() writes to the output stream and appends a newline. */
    public function testLine1(): void
    {
        $stream = fopen("php://memory", "w+");
        $app = new XRay($this->createApplication());
        $app->setOutStream($stream);
        $app->line("test message");
        fseek($stream, 0, SEEK_SET);
        self::assertEquals("test message\n", fgets($stream));
        fclose($stream);
    }

    /** Ensure errorLine() writes colour text to the error stream and appends a newline. */
    public function testErrorLine1(): void
    {
        $stream = fopen("php://memory", "w+");
        $app = new XRay($this->createApplication());
        $app->setErrorStream($stream);
        $app->errorLine("test error");
        fseek($stream, 0, SEEK_SET);
        self::assertEquals("\033[31mtest error\033[39m\n", fgets($stream));
        fclose($stream);
    }

    /** Ensure read() reads a line from the input stream and discards the trailing newline. */
    public function testRead1(): void
    {
        $stream = fopen("php://memory", "w+");
        fputs($stream, "input-text\n");
        fseek($stream, 0, SEEK_SET);
        $app = new XRay($this->createApplication());
        $app->setInStream($stream);
        $actual = $app->read();
        fclose($stream);
        self::assertEquals("input-text", $actual);
    }

    /** Ensure read() writes the prompt to the output stream. */
    public function testRead2(): void
    {
        $inStream = fopen("php://memory", "w+");
        $outStream = fopen("php://memory", "w+");
        fputs($inStream, "input-text\n");
        fseek($inStream, 0, SEEK_SET);
        $app = new XRay($this->createApplication());
        $app->setInStream($inStream);
        $app->setOutStream($outStream);
        $actualInput = $app->read("input something: ");
        fseek($outStream, 0, SEEK_SET);
        $actualPrompt = fread($outStream, 1024);
        fclose($inStream);
        fclose($outStream);
        self::assertEquals("input-text", $actualInput);
        self::assertEquals("input something: ", $actualPrompt);
    }

    /** Ensure read() discards characters beyond max length. */
    public function testRead3(): void
    {
        $stream = fopen("php://memory", "w+");
        fputs($stream, "input-text\n");
        fseek($stream, 0, SEEK_SET);
        $app = new XRay($this->createApplication());
        $app->setInStream($stream);
        $actual = $app->read("", 6);
        fclose($stream);
        self::assertEquals("input-", $actual);
    }

    /** Ensure we get the expected exception when reading the input stream fails. */
    public function testRead4(): void
    {
        $app = new XRay($this->createApplication());
        $this->mockFunction("fgets", false);
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Failed to read from input stream");
        $app->read();

    }

    /** Ensure readSecret() turns input echo off and back on. */
    public function testReadSecret1(): void
    {
        $inStream = fopen("php://memory", "w+");
        fputs($inStream, "secret-input");
        fseek($inStream, 0, SEEK_SET);
        $app = new XRay($this->createApplication());

        $count = 0;

        $shellExec = static function(string $command) use (&$count, $app, $inStream): string {
            ++$count;

            if (1 === $count) {
                TestCase::assertEquals("stty -g", $command);
                return "echo_back_on";
            } elseif (2 === $count) {
                TestCase::assertEquals("stty -echo", $command);

                // once we receive this call, we want the app to read input from our test stream, otherwise it will lock
                // waitin for actual user input
                $app->setInStream($inStream);

                return "-echo";
            } elseif (3 === $count) {
                TestCase::assertEquals("stty echo_back_on", $command);
                return "-echo";
            }

            return "";
        };

        $this->mockFunction("shell_exec", $shellExec);

        // stop readSecret() from writing a newline to stdout
        $outStream = fopen("php://memory", "w+");
        $app->setOutStream($outStream);

        $app->readSecret();
        fclose($outStream);
        self::assertEquals(3, $count);
    }

    /** TODO Ensure readSecret() fails when input is not STDIN */
    /** TODO Ensure linefeed is written to stdout in readSecret() */

    /** TODO Ensure confirm() accepts all expected positive responses. */
    /** TODO Ensure confirm() returns negative for all other responses. */

    /** Ensure we can set the output stream. */
    public function testSetOutputStream1(): void
    {
        $app = $this->createApplication();
        $stream = fopen("php://memory", "w");
        $app->setOutStream($stream);
        self::assertSame($stream, $app->outStream());
    }

    /** Ensure we can set the error stream. */
    public function testSetErrorStream1(): void
    {
        $app = $this->createApplication();
        $stream = fopen("php://memory", "w");
        $app->setErrorStream($stream);
        self::assertSame($stream, $app->errorStream());
    }

    /** Ensure we can set the input stream. */
    public function testSetInStream1(): void
    {
        $app = $this->createApplication();
        $stream = fopen("php://memory", "r");
        $app->setInStream($stream);
        self::assertSame($stream, $app->inStream());
    }

    /** Ensure the output stream is STDOUT by default. */
    public function testOutStream1(): void
    {
        $app = $this->createApplication();
        self::assertSame(STDOUT, $app->outStream());
    }

    /** Ensure the error stream is STDERR by default. */
    public function testErrorStream1(): void
    {
        $app = $this->createApplication();
        self::assertSame(STDERR, $app->errorStream());
    }

    /** Ensure the output stream is STDIN by default. */
    public function testInStream1(): void
    {
        $app = $this->createApplication();
        self::assertSame(STDIN, $app->inStream());
    }

    protected static function dataForTestArguments1(): iterable
    {
        yield "no-args" => [["command.php",], []];
        yield "one-arg" => [["command.php", "foo",], ["foo",]];
        yield "one-flag" => [["command.php", "--foo",], ["--foo",]];
        yield "one-short-flag" => [["command.php", "-f",], ["-f",]];
        yield "one-option" => [["command.php", "--foo", "foo-value",], ["--foo", "foo-value",]];
        yield "one-short-option" => [["command.php", "-f", "foo-value"], ["-f", "foo-value",]];
        yield "arg-option-and-flag" => [["command.php", "-f", "foo-value", "--bar", "bar-value", "argument",], ["-f", "foo-value", "--bar", "bar-value", "argument",]];
    }

    /**
     * Ensure we can get the raw command-line arguments.
     * @dataProvider dataForTestArguments1
     */
    public function testArguments1(array $cliArgs, array $expectedArgs): void
    {
        $app = $this->createApplication(args: $cliArgs);
        self::assertEquals($expectedArgs, $app->commandLineArguments());
    }

    /** Ensure we can check for defined arguments. */
    public function testHasArgument1(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test-argument", "Test argument.");
        self::assertTrue($app->hasArgument("test-argument"));
    }

    /** Ensure we get false for arguments that aren't defined. */
    public function testHasArgument2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("other-test-argument", "Other test argument.");
        self::assertFalse($app->hasArgument("test-argument"));
    }

    /** Ensure we get false for arguments that aren't defined but an option with the same name is. */
    public function testHasArgument3(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", description: "Test option.");
        self::assertFalse($app->hasArgument("test"));
    }

    /** Ensure we get false for arguments that aren't defined but a flag with the same name is. */
    public function testHasArgument4(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", description: "Test flag.");
        self::assertFalse($app->hasArgument("test"));
    }

    /** Ensure we can check for defined options. */
    public function testHasOption1(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", description: "Test option.");
        self::assertTrue($app->hasOption("test"));
    }

    /** Ensure we can check for defined options by short name. */
    public function testHasOption2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", "t", "Test option.");
        self::assertTrue($app->hasOption("t"));
    }

    /** Ensure we get false for options that aren't defined. */
    public function testHasOption3(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("other-test", description: "Test option.");
        self::assertFalse($app->hasOption("test"));
    }

    /** Ensure we get false for options that aren't defined but an argument with the same name is. */
    public function testHasOption4(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test", "Test argument.");
        self::assertFalse($app->hasOption("test"));
    }

    /** Ensure we get false for options that aren't defined but a flag with the same name is. */
    public function testHasOption5(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", description: "Test flag.");
        self::assertFalse($app->hasOption("test"));
    }

    /** Ensure we get false for options that aren't defined but a flag with the same short name is. */
    public function testHasOption6(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", "t", "Test flag.");
        self::assertFalse($app->hasOption("t"));
    }

    /** Ensure we can check for defined flags. */
    public function testHasFlag1(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", description: "Test flag.");
        self::assertTrue($app->hasFlag("test"));
    }

    /** Ensure we can check for defined flags by short name. */
    public function testHasFlag2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", "t", "Test flag.");
        self::assertTrue($app->hasFlag("t"));
    }

    /** Ensure we get false for flags that aren't defined. */
    public function testHasFlag3(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("other-test", description: "Test option.");
        self::assertFalse($app->hasFlag("test"));
    }

    /** Ensure we get false for flags that aren't defined but an argument with the same name is. */
    public function testHasFlag4(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test", "Test argument.");
        self::assertFalse($app->hasFlag("test"));
    }

    /** Ensure we get false for flags that aren't defined but an option with the same name is. */
    public function testHasFlag5(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", description: "Test option.");
        self::assertFalse($app->hasFlag("test"));
    }

    /** Ensure we get false for flags that aren't defined but an option with the same short name is. */
    public function testHasFlag6(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", "t", "Test option.");
        self::assertFalse($app->hasFlag("t"));
    }

    /** Ensure we can check for argments that are set. */
    public function testArgumentIsSet1(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "test-value",]));
        $app->addArgument("test", "Test argument.", optional: true);
        $app->parseCommandLineArguments();
        self::assertTrue($app->argumentIsSet("test"));
    }

    /** Ensure we get false for argments that aren't set. */
    public function testArgumentIsSet2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test", "Test argument.", optional: true);
        $app->parseCommandLineArguments();
        self::assertFalse($app->argumentIsSet("test"));
    }

    /** Ensure we get the expected exception for argments that aren't defined. */
    public function testArgumentIsSet3(): void
    {
        $app = new XRay($this->createApplication());
        $app->parseCommandLineArguments();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Argument \"test\" is not defined");
        $app->argumentIsSet("test");
    }

    /** Ensure we can check for options that are set. */
    public function testOptionIsSet1(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test", "test-value",]));
        $app->addOption("test", description: "Test argument.", optional: true);
        $app->parseCommandLineArguments();
        self::assertTrue($app->optionIsSet("test"));
    }

    /** Ensure we can check for options that are set by short name. */
    public function testOptionIsSet2(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test", "test-value",]));
        $app->addOption("test", "t", "Test argument.", optional: true);
        $app->parseCommandLineArguments();
        self::assertTrue($app->optionIsSet("t"));
    }

    /** Ensure we get false for options that aren't set. */
    public function testOptionIsSet3(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", description: "Test option.", optional: true);
        $app->parseCommandLineArguments();
        self::assertFalse($app->optionIsSet("test"));
    }

    /** Ensure we get false for options that aren't set using their short names. */
    public function testOptionIsSet4(): void
    {
        $app = new XRay($this->createApplication());
        $app->addOption("test", "t", "Test option.", optional: true);
        $app->parseCommandLineArguments();
        self::assertFalse($app->optionIsSet("t"));
    }

    /** Ensure we get the expected exception for argments that aren't defined. */
    public function testOptionIsSet5(): void
    {
        $app = new XRay($this->createApplication());
        $app->parseCommandLineArguments();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option \"test\" is not defined");
        $app->optionIsSet("test");
    }

    /** Ensure we can get argument values. */
    public function testArgumentValue1(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "test-value",]));
        $app->addArgument("test", "Test argument.");
        $app->parseCommandLineArguments();
        self::assertEquals("test-value", $app->argumentValue("test"));
    }

    /** Ensure we can get default value for optional arguments that aren't set but which have defaults. */
    public function testArgumentValue2(): void
    {
        $app = new XRay($this->createApplication());
        $app->addArgument("test", "Test argument.", optional: true, default: "default-test-value");
        $app->parseCommandLineArguments();
        self::assertEquals("default-test-value", $app->argumentValue("test"));
    }

    /** Ensure get the expected exception when attempting to get the value for an argument that is not defined. */
    public function testArgumentValue3(): void
    {
        $app = new XRay($this->createApplication());
        $app->parseCommandLineArguments();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Argument \"not-test\" is not defined");
        $app->argumentValue("not-test");
    }

    /** Ensure we can get option values. */
    public function testOptionValue1(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test", "test-value",]));
        $app->addOption("test", description: "Test option.");
        $app->parseCommandLineArguments();
        self::assertEquals("test-value", $app->optionValue("test"));
    }

    /** Ensure we can get option values using their short names. */
    public function testOptionValue2(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test", "test-value",]));
        $app->addOption("test", "t", "Test option.");
        $app->parseCommandLineArguments();
        self::assertEquals("test-value", $app->optionValue("t"));
    }

    /** Ensure get the expected exception when attempting to get the value for an option that is not defined. */
    public function testOptionValue3(): void
    {
        $app = new XRay($this->createApplication());
        $app->parseCommandLineArguments();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Option \"not-test\" is not defined");
        $app->optionValue("not-test");
    }

    /** Ensure we can get flag values. */
    public function testFlagValue1(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test",]));
        $app->addFlag("test", description: "Test flag.");
        $app->parseCommandLineArguments();
        self::assertTrue($app->flagValue("test"));
    }

    /** Ensure we can get flag values by their short names. */
    public function testFlagValue2(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test",]));
        $app->addFlag("test", "t", "Test flag.");
        $app->parseCommandLineArguments();
        self::assertTrue($app->flagValue("t"));
    }

    /** Ensure we can get flag values by short name when they're specified by long name. */
    public function testFlagValue3(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "--test",]));
        $app->addFlag("test", "t", "Test flag.");
        $app->parseCommandLineArguments();
        self::assertTrue($app->flagValue("t"));
    }

    /** Ensure we can get flag values by name when they're specified by short name. */
    public function testFlagValue4(): void
    {
        $app = new XRay($this->createApplication(args: ["test-command.php", "-t",]));
        $app->addFlag("test", "t", "Test flag.");
        $app->parseCommandLineArguments();
        self::assertTrue($app->flagValue("test"));
    }

    /** Ensure we can get flag values for unset flags. */
    public function testFlagValue5(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", description: "Test flag.");
        $app->parseCommandLineArguments();
        self::assertFalse($app->flagValue("test"));
    }

    /** Ensure we can get flag values for unset flags by their short names. */
    public function testFlagValue6(): void
    {
        $app = new XRay($this->createApplication());
        $app->addFlag("test", "t", "Test flag.");
        $app->parseCommandLineArguments();
        self::assertFalse($app->flagValue("t"));
    }

    /** Ensure get the expected exception when attempting to get the value for an flag that is not defined. */
    public function testFlagValue7(): void
    {
        $app = new XRay($this->createApplication());
        $app->parseCommandLineArguments();
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Flag \"test\" is not defined");
        self::assertTrue($app->flagValue("test"));
    }

    /** TODO test validateCommandLineArguments() */
}
