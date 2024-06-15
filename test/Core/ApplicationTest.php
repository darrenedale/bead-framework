<?php

namespace BeadTests\Core;

use Bead\Contracts\FeatureFlag as FeatureFlagContract;
use Bead\Core\Application;
use Bead\Core\FeatureFlag;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Testing\XRay;
use PHPUnit\Framework\TestCase;

use Stringable as Stringable;
use function is_array;

class ApplicationTest extends TestCase
{
    private const TestConfig = [
        "app" => [
            "feature-flags" => [
                "feature-flag-1" => "variant-1",
                "feature-flag-2" => null,
            ],
        ],
        "mail" => [
            "transport" => "mailgun",
            "transports" => [
                "php" => [
                    "driver" => "php",
                ],

                "mailgun" => [
                    "driver" => "mailgun",
                    "endpoint" => "https://api.eu.mailgun.net",
                    "key" => "some key",
                ],

                "fake" => [
                    "driver" => "fake",
                    "values" => [
                        "embedded" => true,
                        "descend" => [
                            "key" => "value",
                        ],
                    ],
                ],
            ],
        ],
        "dotted" => [
            "with" => [
                "dot" => "not this",
            ],
            "with.dot" => "but this",
        ],
    ];

    private Application $m_app;

    public function setUp(): void
    {
        $this->m_app = new class extends Application
        {
            public function __construct()
            {
            }

            public function exec(): int
            {
                return self::ExitOk;
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->m_app);
    }

    public function testReplaceService(): void
    {
        $original = (object) ["foo" => "bar",];
        $replacement = (object) ["fox" => "bax",];
        $this->m_app->bindService("foo", $original);

        self::assertSame($original, $this->m_app->service("foo"));
        $acutal = $this->m_app->replaceService("foo", $replacement);
        self::assertSame($original, $acutal);
        self::assertSame($replacement, $this->m_app->service("foo"));
    }

    public function testBindService(): void
    {
        $original = (object) ["foo" => "bar",];
        $replacement = (object) ["fox" => "bax",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->service("foo"));

        $this->expectException(ServiceAlreadyBoundException::class);
        $this->m_app->bindService("foo", $replacement);
    }

    public function testService(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->service("foo"));
    }

    public function testServiceIsBound(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertFalse($this->m_app->serviceIsBound("fox"));
    }

    public function testGet(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->get("foo"));
    }

    public function testHas(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->has("foo"));
        self::assertFalse($this->m_app->has("fox"));
    }

    public static function dataForTestConfig1(): iterable
    {
        yield "whole-file-config" => ["mail", null, self::TestConfig["mail"],];
        yield "non-existent-file" => ["foo", null, null,];
        yield "non-existent-file-default" => ["foo", "bar", "bar",];
        yield "top-level-from-file" => ["mail.transport", null, "mailgun",];
        yield "nested-in-file" => ["mail.transports.mailgun.key", null, "some key",];
        yield "nested-doesn't-exist" => ["mail.transports.fake.key", null, null,];
        yield "nested-doesn't-exist-default" => ["mail.transports.fake.key", "foo", "foo",];
        yield "nested-array" => ["mail.transports.fake.values", null, self::TestConfig["mail"]["transports"]["fake"]["values"],];
        yield "prefers-actual-key-to-array-descent" => ["dotted.with.dot", null, "but this",];
    }

    /**
     * Ensure the config is traversed correctly.
     *
     * @dataProvider dataForTestConfig1
     */
    public function testConfig1(string $key, mixed $default, mixed $expected): void
    {
        $xRay = new XRay($this->m_app);
        $xRay->m_config = self::TestConfig;

        if (null === $default) {
            $actual = $this->m_app->config($key);
        } else {
            $actual = $this->m_app->config($key, $default);
        }

        if (is_array($expected)) {
            self::assertEqualsCanonicalizing($expected, $actual);
        } else {
            self::assertEquals($expected, $actual);
        }
    }

    /** Ensure feature flags are not read from config until required */
    public function testReadFeatureFlags1()
    {
        $app = new XRay($this->m_app);
        $app->m_config = self::TestConfig;
        self::assertNull($app->m_featureFlags);
    }

    /** Ensure feature flags are successfully read from the app config */
    public function testReadFeatureFlags2()
    {
        $app = new XRay($this->m_app);
        $app->m_config = self::TestConfig;
        $app->readFeatureFlags();
        self::assertIsArray($app->m_featureFlags);

        $flags = [];

        foreach ($app->m_featureFlags as $flag) {
            self::assertInstanceOf(FeatureFlagContract::class, $flag);
            $flags[$flag->feature()] = $flag->variant();
        }

        self::assertEqualsCanonicalizing(
            [
                "feature-flag-1" => "variant-1",
                "feature-flag-2" => null,
            ],
            $flags
        );
    }

    /** Ensure feature flags are empty when the config doesn't have any */
    public function testReadFeatureFlags3()
    {
        $app = new XRay($this->m_app);
        $config = self::TestConfig;
        unset($config['app']['feature-flags']);
        $app->m_config = $config;
        $app->readFeatureFlags();
        self::assertIsArray($app->m_featureFlags);
        self::assertCount(0, $app->m_featureFlags);
    }

    /** Ensure reading feature flags throws when the config isn't an array */
    public function testReadFeatureFlags4()
    {
        $app = new XRay($this->m_app);
        $config = self::TestConfig;
        $config['app']['feature-flags'] = "invalid-flags";
        $app->m_config = $config;
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting array of feature flags, found string");
        $app->readFeatureFlags();
    }

    /** Ensure reading feature flags throws when the config contains an invalid feature name. */
    public function testReadFeatureFlags5()
    {
        $app = new XRay($this->m_app);
        $config = self::TestConfig;
        $config['app']['feature-flags'][1] = "invalid-feature-name";
        $app->m_config = $config;
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting string feature flag, found int");
        $app->readFeatureFlags();
    }

    protected static function dataForTestReadFeatureFlags6(): iterable
    {
        yield "int" => [42, "int",];
        yield "double" => [3.1415927, "double",];
        yield "bool-true" => [true, "bool",];
        yield "bool-false" => [false, "bool",];
        yield "object" => [(object) [], "object",];
        yield "stringable" => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return "the-variant";
                }
            },
            null,       // class name can't be determined
        ];
    }

    /**
     * Ensure reading feature flags throws when the config contains an invalid feature name.
     *
     * @dataProvider dataForTestReadFeatureFlags6
     */
    public function testReadFeatureFlags6(mixed $variant, ?string $expectedInvalidType = null)
    {
        $app = new XRay($this->m_app);
        $config = self::TestConfig;
        $config["app"]["feature-flags"]["feature-flag-3"] = $variant;
        $app->m_config = $config;
        self::expectException(InvalidConfigurationException::class);

        if (null !== $expectedInvalidType) {
            self::expectExceptionMessageMatches("/^Expecting string or null feature variant, found /");
        } else {
            self::expectExceptionMessage("Expecting string or null feature variant, found {$expectedInvalidType}");
        }

        $app->readFeatureFlags();
    }

    /** Ensure we retrieve the expected feature flags. */
    public function testFeatureFlags1(): void
    {
        $app = new XRay($this->m_app);
        $app->m_config = self::TestConfig;
        $actualFlags = $this->m_app->featureFlags();
        self::assertIsArray($actualFlags);
        self::assertCount(2, $actualFlags);
        $actualFlagsArray = [];

        foreach ($actualFlags as $flag) {
            $actualFlagsArray[$flag->feature()] = $flag->variant();
        }

        self::assertEqualsCanonicalizing(self::TestConfig["app"]["feature-flags"], $actualFlagsArray);
    }

    protected static function definedFeatureFlags(): iterable
    {
        foreach (array_keys(self::TestConfig["app"]["feature-flags"]) as $feature) {
            yield $feature => [$feature];
            yield "{$feature}-upper" => [mb_strtoupper($feature)];
            yield "{$feature}-lower" => [mb_strtolower($feature)];
            yield "{$feature}-title" => [mb_convert_case($feature, MB_CASE_TITLE)];
        };
    }

    /**
     * Ensure we can tell feature flags that are defined, case-insensitively.
     *
     * @dataProvider definedFeatureFlags
     */
    public function testHasFeatureFlag1(string $feature): void
    {
        $app = new Xray($this->m_app);
        $app->m_config = self::TestConfig;
        self::assertTrue($this->m_app->hasFeatureFlag($feature));
    }

    protected static function undefinedFeatureFlags(): iterable
    {
        yield "empty" => [""];
        yield "whitespace" => [" "];
        yield "multiple-whitespace" => ["   "];

        foreach (array_keys(self::TestConfig["app"]["feature-flags"]) as $feature) {
            yield "{$feature}-leading-space" => [" {$feature}"];
            yield "{$feature}-trailing-space" => ["{$feature} "];
            yield "{$feature}-surrounding-space" => [" {$feature} "];
        }
    }

    /**
     * Ensure we can tell feature flags that are not defined.
     *
     * @dataProvider undefinedFeatureFlags
     */
    public function testHasFeatureFlag2(string $feature): void
    {
        $app = new Xray($this->m_app);
        $app->m_config = self::TestConfig;
        self::assertFalse($this->m_app->hasFeatureFlag($feature));
    }

    /**
     * Ensure we get the expected feature flag.
     *
     * @param string $feature
     * @dataProvider definedFeatureFlags
     */
    public function testFeatureFlag1(string $feature): void
    {
        $app = new Xray($this->m_app);
        $app->m_config = self::TestConfig;
        $expectedVariant = self::TestConfig["app"]["feature-flags"][mb_strtolower($feature)];
        $actualFlag = $this->m_app->featureFlag($feature);
        self::assertInstanceOf(FeatureFlagContract::class, $actualFlag);
        self::assertEquals(mb_strtolower($feature), $actualFlag->feature());
        self::assertSame($expectedVariant, $actualFlag->variant());
    }

    /**
     * Ensure we don't get the undefined feature flags.
     *
     * @param string $feature
     * @dataProvider undefinedFeatureFlags
     */
    public function testFeatureFlag2(string $feature): void
    {
        $app = new Xray($this->m_app);
        $app->m_config = self::TestConfig;
        $actualFlag = $this->m_app->featureFlag($feature);
        self::assertNull($actualFlag);
    }
}
