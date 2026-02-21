<?php

namespace Bead\Core\Binders;

use Bead\Contracts\Authentication\Authenticator as AuthenticatorContract;
use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Core\Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Web\Application as WebApplication;

/**
 * Bind an Authenticator service into the application.
 *
 * The bound authenticator is configured in the auth config file.
 */
class Authenticator implements BinderContract
{
    /**
     * Determine whether the authenticator should be bound into the application service container.
     *
     * Customisation point for apps to tweak the logic to determine when to bind the configured authenticator.
     *
     * The default implementation will bind if the Application instance is a Web\Application.
     *
     * @param Application $app The current Application instance.
     */
    protected function shouldBindAuthenticator(Application $app): bool
    {
        return $app instanceof WebApplication;
    }

    /**
     * Create the authenticator instance from the config.
     *
     * Customisation point for apps to tweak how the authenticator is created.
     *
     * @param array $config The authenticator configuration specified in the auth config file.
     * @throws InvalidConfigurationException if the configured authenticator class does not implement
     * AuthenticatorContract.
     */
    protected function createAuthenticator(array $config): AuthenticatorContract
    {
        $authenticatorClass = $config["authenticator-class"];

        if (!is_a($authenticatorClass, AuthenticatorContract::class, true)) {
            throw new InvalidConfigurationException("auth.authenticators.[auth.authenticator].authenticator-class", "Expected class implementing " . AuthenticatorContract::class . " contract, found {$authenticatorClass}");
        }

        return new $authenticatorClass(...($config["authenticator-args"] ?? []));
    }

    /**
     * Set the model class the configured authenticator works with.
     *
     * Customisation point for apps to tweak how the authenticator's model class is set.
     *
     * @param array $config The authenticator configuration specified in the auth config file.
     * @throws InvalidConfigurationException if the configured model class does not implement AuthenticatableContract.
     */
    protected function setAuthenticatableModel(AuthenticatorContract $authenticator, array $config): void
    {
        $modelClass = $config["model-class"];

        if (!is_a($modelClass, AuthenticatableContract::class, true)) {
            throw new InvalidConfigurationException("auth.authenticators.[auth.authenticator].model-class", "Expected class implementing " . AuthenticatableContract::class . " contract, found {$modelClass}");
        }

        $authenticator->authenticateInstancesOf($modelClass);
    }

    /**
     * Bind the configured authenticator into the application.
     * @throws InvalidConfigurationException if either the configured authenticator class or the configured model class
     * don't implement the appropriate interfaces.
     * @throws ServiceAlreadyBoundException if an authenticator service is already bound into the container.
     */
    public function bindServices(Application $app): void
    {
        if (!$this->shouldBindAuthenticator($app)) {
            return;
        }

        $config = $app->config("auth.authenticators");
        $configuredAuthenticator = $app->config("auth.authenticator");
        $authenticator = $this->createAuthenticator($config[$configuredAuthenticator]);
        $this->setAuthenticatableModel($authenticator, $config[$configuredAuthenticator]);
        $app->bindService(AuthenticatorContract::class, $authenticator);
    }
}
