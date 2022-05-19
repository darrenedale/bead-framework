<?php

namespace Equit\Contracts;

interface Response
{
	public function statusCode(): int;
	public function contentType(): string;
	public function headers(): array;
	public function content(): string;
	public function send(): void;
}
