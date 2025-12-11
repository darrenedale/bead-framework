<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

class UriUserInfo implements UriUserInfoContract
{
    private string $m_username;

    private ?string $m_password;

    public function __construct(string $username, ?string $password = null)
    {
        $this->m_username = $username;
        $this->m_password = $password;
    }

    /** @inheritDoc */
    public function username(): string
    {
        return $this->m_username;
    }

    public function withUsername(string $username): static
    {
        $clone = clone $this;
        $clone->m_username = $username;
        return $clone;
    }

    /** @inheritDoc */
    public function password(): ?string
    {
        return $this->m_password;
    }

    public function withPassword(string $password): static
    {
        $clone = clone $this;
        $clone->m_password = $password;
        return $clone;
    }

    public function withoutPassword(): static
    {
        $clone = clone $this;
        $clone->m_password = null;
        return $clone;
    }

    public function __toString(): string
    {
        $ui = rawurlencode($this->username());

        if (null !== $this->password()) {
            $ui .= ":" . rawurlencode($this->password());
        }

        return $ui;
    }
}
