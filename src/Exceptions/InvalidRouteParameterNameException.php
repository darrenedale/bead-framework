<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

namespace Bead\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when an attempt is made to register a route that has an invalid parameter name in it.
 */
class InvalidRouteParameterNameException extends Exception
{
    /** @var string The invalid parameter name. */
    private string $m_parameterName;

    /** @var string The route with the invalid parameter name. */
    private string $m_route;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $parameterName The invalid parameter name.
     * @param string $route The route with the invalid parameter name.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param \Throwable|null $previous The optional previous Throwable. Defaults to null.
     */
    public function __construct(string $parameterName, string $route, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_parameterName = $parameterName;
        $this->m_route = $route;
    }

    /**
     * Fetch the invalid parameter name.
     *
     * @return string The route.
     */
    public function getParameterName(): string
    {
        return $this->m_parameterName;
    }

    /**
     * Fetch the route with the invalid parameter name.
     *
     * @return string The route.
     */
    public function getRoute(): string
    {
        return $this->m_route;
    }
}
