<?php

namespace Bead\Contracts\Web;

interface Response
{
    public function statusCode(): int;

    public function reasonPhrase(): string;

    public function contentType(): string;

    /** @return Header[] */
    public function headers(): array;

    public function content(): string;

    public function send(): void;
}
