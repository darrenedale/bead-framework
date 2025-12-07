<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;
use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

class UriAuthority implements UriAuthorityContract
{
    private ?UriUserInfoContract $m_userInfo;

    private string $m_host;

    private ?int $m_port;

    public function __construct(string $host, ?int $port = null, ?UriUserInfoContract $userInfo = null)
    {
        $this->m_userInfo = $userInfo;
        $this->m_host = $host;
        $this->m_port = $port;
    }

    public function __clone(): void
    {
        if (null !== $this->m_userInfo) {
            $this->m_userInfo = clone $this->m_userInfo;
        }
    }

    public function userInfo(): ?UriUserInfo
    {
        return $this->m_userInfo;
    }

    public function withUserInfo(UriUserInfoContract $userInfo): static
    {
        $clone = clone $this;
        $clone->m_userInfo = $userInfo;
        return $clone;
    }

    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->m_userInfo = null;
        return $clone;
    }

    public function withUsername(string $username): static
    {
        $clone = clone $this;

        if (null !== $clone->m_userInfo) {
            $clone->m_userInfo = $clone->m_userInfo->withUsername($username);
        } else {
            $clone->m_userInfo = new UriUserInfo($username);
        }

        return $clone;
    }

    public function withUsernameAndPassword(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_userInfo = new UriUserInfo($username, $password);
        return $clone;
    }

    public function withPassword(string $password): static
    {
        $clone = clone $this;

        if (null !== $clone->m_userInfo) {
            $clone->m_userInfo = $clone->m_userInfo->withPassword($password);
        } else {
            $clone->m_userInfo = new UriUserInfo("", $password);
        }

        return $clone;
    }

    public function withoutPassword(): static
    {
        $clone = clone $this;

        if (null !== $clone->m_userInfo) {
            $clone->m_userInfo = $clone->m_userInfo->withoutPassword();
        }

        return $clone;
    }

    public function host(): string
    {
        return $this->m_host;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->m_host = $host;
        return $clone;
    }

    public function port(): ?int
    {
        return $this->m_port;
    }

    public function withPort(int $port): static
    {
        $clone = clone $this;
        $clone->m_port = $port;
        return $clone;
    }

    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->m_port = null;
        return $clone;
    }
    public function __toString(): string
    {
        $ui = "";

        if (null !== $this->userInfo()) {
            $ui = "{$this->userInfo()}@";
        }

        $ui .= $this->host();

        if (null !== $this->port()) {
            $ui .= ":{$this->port()}";
        }

        return $ui;
    }
}
