<?php

declare(strict_types=1);

namespace BeadTests\Core\Binders;

use Bead\Contracts\Email\Transport as TransportAlias;
use Bead\Core\Application;
use Bead\Core\Binders\MailTransport;
use Bead\Email\Transport\Mailgun;
use Bead\Email\Transport\Php;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class MailTransportTest extends TestCase
{
    private MailTransport $binder;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockMethod(Mailgun::class, "isAvailable", true);
        $this->binder = new MailTransport();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->binder);
        parent::tearDown();
    }

    private function setMailConfig(array|null $config): void
    {
        $this->app->shouldReceive("config")
            ->with("mail.transport")
            ->andReturn($config ? ($config["transport"] ?? null) : null);
    }

    public function testCreatePhpTransport1(): void
    {
        $binder = new XRay($this->binder);
        self::assertInstanceOf(Php::class, $binder->createPhpTransport([]));
    }

    public static function dataForTestCreateMailgunTransport1(): iterable
    {
        yield "domain-and-key" => [["domain" => "bead.equit.dev", "key" => "the-key",],];
        yield "domain-key-and-endpoint" => [["domain" => "bead-framework.equit.dev", "key" =>  "the-mailgun-key", "endpoint" => "https://api.eu.mailgun.net",],];
    }

    /**
     * @dataProvider dataForTestCreateMailgunTransport1
     *
     * @param array $config The Mailgun transport configuration to test with.
     */
    public function testCreateMailgunTransport1(array $config): void
    {
        $binder = new XRay($this->binder);
        $actual = $binder->createMailgunTransport($config);
        self::assertInstanceOf(Mailgun::class, $actual);
    }

    /** Ensure createMailgunTransport() throws when no domain is given */
    public function testCreateMailgunTransport2(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun domain, found none");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["key" => "the-key",]);
    }

    /** Ensure createMailgunTransport() throws when the domain is null. */
    public function testCreateMailgunTransport3(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun domain, found none");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["domain" => null, "key" => "the-key",]);
    }

    /** Ensure createMailgunTransport() throws when the domain is empty. */
    public function testCreateMailgunTransport4(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun domain, found \"\"");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["domain" => "", "key" => "the-key",]);
    }

    /** Ensure createMailgunTransport() throws when the domain is not a string. */
    public function testCreateMailgunTransport5(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun domain, found int");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["domain" => 42, "key" => "the-key",]);
    }

    /** Ensure createMailgunTransport() throws when the domain is an object. */
    public function testCreateMailgunTransport6(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun domain, found " . $this::class);
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["domain" => $this, "key" => "the-key",]);
    }

    /** Ensure createMailgunTransport() throws when no key is given */
    public function testCreateMailgunTransport7(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun key, found none");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["domain" => "the-domain",]);
    }

    /** Ensure createMailgunTransport() throws when the key is null. */
    public function testCreateMailgunTransport8(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun key, found none");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["key" => null, "domain" => "the-domain",]);
    }

    /** Ensure createMailgunTransport() throws when the key is empty. */
    public function testCreateMailgunTransport9(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun key, found \"\"");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["key" => "", "domain" => "the-domain",]);
    }

    /** Ensure createMailgunTransport() throws when the key is not a string. */
    public function testCreateMailgunTransport10(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun key, found int");
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["key" => 42, "domain" => "the-domain",]);
    }

    /** Ensure createMailgunTransport() throws when the key is an object. */
    public function testCreateMailgunTransport11(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid mailgun key, found " . $this::class);
        $binder = new XRay($this->binder);
        $binder->createMailgunTransport(["key" => $this, "domain" => "the-domain",]);
    }

    public static function dataForTestCreateTransport1(): iterable
    {
        yield "php" => ["php", ["driver"=> "php",], Php::class,];
        yield "mailgun" => ["mailgun", ["driver" => "mailgun", "domain" => "domain", "key" => "key",], Mailgun::class,];
    }

    /**
     * Ensure createTransport() creates the expected transports.
     *
     * @dataProvider dataForTestCreateTransport1
     *
     * @param string $transport The name of the transport being created.
     * @param array $config The configuration for the transport.
     */
    public function testCreateTransport(string $transport, array $config, string $class): void
    {
        $binder = new XRay($this->binder);
        self::assertInstanceOf($class, $binder->createTransport($transport, $config));
    }

    /** Ensure createTransport() throws with an unrecognised driver. */
    public function testCreateTransport2(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting supported transport driver, found \"not-a-driver\"");
        $binder = new XRay($this->binder);
        $binder->createTransport("foo", ["driver" => "not-a-driver",]);
    }

    /** Ensure bindServices() creates the expected transport. */
    public function testBindServices1(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport")
            ->andReturn("php");

        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transports.php")
            ->andReturn(["driver" => "php",]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(
                TransportAlias::class,
                Mockery::on(fn (mixed $service): bool => $service instanceof Php)
            );

        $this->binder->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() does nothing when no config is set. */
    public function testBindServices2(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport")
            ->andReturn(null);

        $this->app->shouldNotReceive("bindService");
        $this->binder->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() throws with a non-string transport. */
    public function testBindServices3(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport")
            ->andReturn(42);

        $this->app->shouldNotReceive("bindService");
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionmessage("Expected string transport name, found int");
        $this->binder->bindServices($this->app);
    }

    /** Ensure bindServices() throws when the configured transport's config is not given. */
    public function testBindServices4(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport")
            ->andReturn("php");

        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transports.php")
            ->andReturn(null);

        $this->app->shouldNotReceive("bindService");
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionmessage("Expected transport configuration array, found none");
        $this->binder->bindServices($this->app);
    }

    /** Ensure bindServices() throws when the configured transport's config is not an array. */
    public function testBindServices6(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport")
            ->andReturn("php");

        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transports.php")
            ->andReturn("");

        $this->app->shouldNotReceive("bindService");
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionmessage("Expected transport configuration array, found string");
        $this->binder->bindServices($this->app);
    }
}
