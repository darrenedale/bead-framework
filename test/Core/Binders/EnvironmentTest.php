<?php

declare(strict_types=1);

namespace BeadTests\Core\Binders;

use Bead\Core\Application;
use Bead\Core\Binders\Environment;
use Bead\Environment\Sources\File;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class EnvironmentTest extends TestCase
{
    private Environment $environment;

    public function setUp(): void
    {
        $this->environment = new Environment();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->environment);
        parent::tearDown();
    }

    /** @return Application&MockInterface */
    private function createMockApplication(): Application
    {
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);
        return $app;
    }

    /** Ensure the binder reads the sources from the configuration file. */
    public function testEnvironmentSources1(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.environments")
            ->once()
            ->andReturn(["bead", "framework",]);

        $environment = new XRay($this->environment);
        self::assertEquals(["bead", "framework",], $environment->environmentSources($app));
    }

    /** Ensure an empty array of sources is returned when none are configured. */
    public function testEnvironmentSources2(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.environments")
            ->once()
            ->andReturn(null);

        $environment = new XRay($this->environment);
        $actual = $environment->environmentSources($app);
        self::assertIsArray($actual);
        self::assertEmpty($actual);
    }

    /** Ensure the binder throws when an invalid environment source is found. */
    public function testEnvironmentSources3(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.environments")
            ->once()
            ->andReturn(["bead", 1, "framework",]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting an array of environments, found a non-string entry");
        $environment = new XRay($this->environment);
        $environment->environmentSources($app);
    }

    /** Ensure the binder reads the config for a source from the config file. */
    public function testSourceConfig1(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.sources")
            ->twice()
            ->andReturn([
                "bead" => [
                    "driver" => "env",
                ],
                "framework" => [
                    "driver" => "file",
                    "path" => ".env",
                ],
            ]);

        $environment = new XRay($this->environment);
        $actual = $environment->sourceConfig("bead", $app);
        self::assertEquals(["driver" => "env",], $actual);
        $actual = $environment->sourceConfig("framework", $app);

        self::assertEquals([
            "driver" => "file",
            "path" => ".env",
        ], $actual);
    }

    /** Ensure the binder throws if the config doesn't exist. */
    public function testSourceConfig2(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.sources")
            ->once()
            ->andReturn(null);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected configuration for environment sources to be an array, found NULL");
        $environment = new XRay($this->environment);
        $environment->sourceConfig("bead", $app);
    }

    /** Ensure the binder throws if the config isn't an array. */
    public function testSourceConfig3(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.sources")
            ->once()
            ->andReturn("bead");

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected configuration for environment sources to be an array, found string");
        $environment = new XRay($this->environment);
        $environment->sourceConfig("bead", $app);
    }

    /** Ensure the binder throws if the config doesn't contain config for a source it's expecting. */
    public function testSourceConfig4(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.sources")
            ->once()
            ->andReturn([
                "bead" => [
                    "driver" => "env",
                ],
            ]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected configuration for source \"framework\", none found");
        $environment = new XRay($this->environment);
        $environment->sourceConfig("framework", $app);
    }

    /** Ensure the binder throws if the config for the source isn't an array. */
    public function testSourceConfig5(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("config")
            ->with("env.sources")
            ->once()
            ->andReturn(["bead" => "framework",]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected configuration for source \"bead\" to be an array, found string");
        $environment = new XRay($this->environment);
        $environment->sourceConfig("bead", $app);
    }

    /** Ensure a File environment source can successfully be created. */
    public function testCreateFileSource1(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("rootDir")
            ->twice()
            ->andReturn(__DIR__);

        $environment = new XRay($this->environment);
        $file = $environment->createFileSource(["path" => "files/test-environment-file-01.env",], $app);
        self::assertInstanceOf(File::class, $file);
        self::assertTrue($file->has("BEAD"));
        self::assertEquals("FRAMEWORK", $file->get("BEAD"));
        self::assertCount(1, $file->all());
    }

    /** Ensure createFileSource() throws when the config has no path. */
    public function testCreateFileSource2(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting valid path for File environment source, found none");
        $environment = new XRay($this->environment);
        $environment->createFileSource(["path " => "files/test-environment-file-01.env",], $this->createMockApplication());
    }

    /** Ensure createFileSource() throws when the config's path is not a string. */
    public function testCreateFileSource3(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting valid path for File environment source, found double");
        $environment = new XRay($this->environment);
        $environment->createFileSource(["path" => 3.1415927,], $this->createMockApplication());
    }

    /** Ensure createFileSource() throws when the config's path cannot be canonicalised. */
    public function testCreateFileSource4(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("rootDir")
            ->once()
            ->andReturn(__DIR__);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting valid path for File environment source, found \"files/does-not-exist.env\"");
        $environment = new XRay($this->environment);
        $environment->createFileSource(["path" => "files/does-not-exist.env",], $app);
    }

    /** Ensure createFileSource() throws when the config's path is not inside the app's root dir. */
    public function testCreateFileSource5(): void
    {
        $app = $this->createMockApplication();
        $app->shouldReceive("rootDir")
            ->twice()
            ->andReturn(__DIR__ . "/files");

        $this->mockFunction("str_starts_with", false);
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting path inside application root directory, found \"../EnvironmentTest.php\"");
        $environment = new XRay($this->environment);
        $environment->createFileSource(["path" => "../EnvironmentTest.php",], $app);
    }

    /** Ensure createFileSource() throws when the File source constructor throws. */
    public function testCreateFileSource6(): void
    {

    }
}
