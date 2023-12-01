<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application;
use Bead\Core\Translator as BeadTranslator;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use InvalidArgumentException;

use function gettype;
use function is_string;

/** Binds the configured translator into the service container. */
class Translator implements BinderContract
{
    /**
     * Create the Translator instance to bind to the contract.
     *
     * @param Application $app
     * @return TranslatorContract
     * @throws InvalidConfigurationException
     */
    protected static function createTranslator(Application $app): TranslatorContract
    {
        $language = $app->config("app.language", "en-GB");

        if (!is_string($language)) {
            throw new InvalidConfigurationException("app.language", "Expected valid language, found " . gettype($language));
        }

        try {
            $translator = new BeadTranslator($language);
        } catch (InvalidArgumentException $err) {
            throw new InvalidConfigurationException("app.language", $err->getMessage(), previous: $err);
        }

        $translator->addSearchPath("{$app->rootDir()}/i18n");
        return $translator;
    }

    /**
     * @param Application $app
     * @throws InvalidConfigurationException if the language is misconfigured.
     * @throws ServiceAlreadyBoundException if a translator is already bound.
     */
    public function bindServices(Application $app): void
    {
        $app->bindService(TranslatorContract::class, static::createTranslator($app));
    }
}
