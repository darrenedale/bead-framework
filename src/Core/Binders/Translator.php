<?php
declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application;
use Bead\Core\Translator as BeadTranslator;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;

use function gettype;
use function is_string;

/**
 * Binds the configured translator into the service container.
 */
class Translator implements BinderContract
{
    /**
     * @param Application $app
     * @throws InvalidConfigurationException if the language is misconfigured.
     * @throws ServiceAlreadyBoundException if a translator is already bound.
     */
    public function bindServices(Application $app): void
    {
        $translator = new BeadTranslator();
        // TODO load path(s) from config
        $translator->addSearchPath("i18n");
        $language = $app->config("app.language", "en-GB");

        if (!is_string($language)) {
            throw new InvalidConfigurationException("app.language", "Expected valid language, found " . gettype($language));
        }

        $translator->setLanguage($language);
        $app->bindService(TranslatorContract::class, $translator);
    }
}