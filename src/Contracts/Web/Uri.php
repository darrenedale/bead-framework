<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Psr\Http\Message\UriInterface;

interface Uri extends UriInterface
{
    public function scheme(): string;

    public function authority(): UriAuthority;

    public function userInfo(): ?UriUserInfo;

    public function username(): ?string;

    public function password(): ?string;

    public function host(): string;

    public function port(): ?int;

    public function path(): string;

    public function query(): string;

    public function fragment(): string;

    public function withUsername(string $username): static;

    public function withPassword(string $password): static;

    public function withoutPassword(): static;

    public function withoutUserInfo(): static;

    public function withAuthority(UriAuthority $authority): static;

    public function withPort(?int $port): static;

    public function withoutPort(): static;

    public function withoutQuery(): static;

    public function withoutFragment(): static;
}
