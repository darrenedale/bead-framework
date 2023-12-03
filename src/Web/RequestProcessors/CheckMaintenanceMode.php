<?php

namespace Bead\Web\RequestProcessors;

use Bead\Contracts\RequestPreprocessor;
use Bead\Contracts\Response;
use Bead\Exceptions\Http\ServiceUnavailableException;
use Bead\Exceptions\ViewNotFoundException;
use Bead\Facades\Application as App;
use Bead\View;
use Bead\Web\Request;
use RuntimeException;

class CheckMaintenanceMode implements RequestPreprocessor
{
    /**
     * The view to use if the application is in maintenance mode.
     *
     * @return string|null The name of the view, or `null` if no view should be used in maintenance mode.
     */
    protected function viewName(): ?string
    {
        return "system.maintenance-mode";
    }

    /**
     * Determine whether the application is in maintenance mode.
     *
     * The default behaviour is to check the value of the app.maintenance config. If this is truthy the app is in
     * maintenance mode; if it's falsy or not set, the app is not in maintenance mode.
     *
     * @return bool `true` if the app is in maintenance, `false` if not.
     * @throws RuntimeException if there is no Application instance.
     */
    protected function isInMaintenanceMode(): bool
    {
        return (bool) App::config("app.maintenance", false);
    }

    /**
     * Check whether the application is in maintenance mode.
     *
     * If the application is in maintenance mode a Response is returned. This is either the view named by viewName(),
     * or a 503 HTTP exception if there is no named view or the view cannot be located.
     *
     * @throws RuntimeException if there is no Application instance.
     * @throws ServiceUnavailableException if no maintenance mode view is available.
     */
    public function preprocessRequest(Request $request): ?Response
    {
        if ($this->isInMaintenanceMode()) {
            $viewName = $this->viewName();
            $previous = null;

            if (null !== $viewName) {
                try {
                    return new View($this->viewName());
                } catch (ViewNotFoundException $err) {
                    $previous = $err;
                }
            }

            throw new ServiceUnavailableException($request, "Application is currently down for maintenance", previous: $previous);
        }

        return null;
    }
}
