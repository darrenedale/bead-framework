<?php

namespace BeadTests\Core\Binders;

use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application;
use Bead\Core\Binders\Translator as TranslatorBinder;
use Bead\Exceptions\InvalidConfigurationException;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

/** Test the bundled translation services binder. */
final class TranslatorTest extends TestCase
{
    private TranslatorBinder $translator;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        $this->translator = new TranslatorBinder();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->translator, $this->app);
        parent::tearDown();
    }

    private function setLanguage(mixed $language): void
    {
        $this->app->shouldReceive("config")
            ->with("app.language", "en-GB")
            ->andReturn($language);
    }

    /** Ensure we can successfully bind a translator with the configured language. */
    public function testBindServices1(): void
    {
        $this->setLanguage("fr-FR");

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(TranslatorContract::class, Mockery::on(fn (mixed $instance): bool => $instance instanceof TranslatorContract && "fr-FR" === $instance->language()));

        $this->translator->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() throws if the language is invalid. */
    public function testBindServices2(): void
    {
        $this->setLanguage(7);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected valid language, found integer");
        $this->translator->bindServices($this->app);
    }
}
