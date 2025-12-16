<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Stringable;

interface Uri extends Stringable
{
    public const SchemeHttp = "http";

    public const SchemeHttps = "https";

    /** Fetch the URI scheme. */
    public function scheme(): string;

    /** Fetch the URI authority section. */
    public function authority(): UriAuthority;

    /** Fetch the URI user info section, if it has one. */
    public function userInfo(): ?UriUserInfo;

    /** Fetch the URI username from the user info section, if it has one. */
    public function username(): ?string;

    /** Fetch the URI password from the user info section, if it has one. */
    public function password(): ?string;

    /** Fetch the URI host from the authority section. */
    public function host(): string;

    /** Fetch the URI port, if it has one, from the authority section. */
    public function port(): ?int;

    /** Fetch the URI path. */
    public function path(): string;

    /**
     * Fetch the URI query string.
     *
     * @return string|null The escaped query string, or null if the URI has no query.
     */
    public function query(): ?string;

    /**
     * Fetch the URI fragment.
     *
     * @return string|null The fragment, or null if the URI has no fragment.
     */
    public function fragment(): ?string;
}
