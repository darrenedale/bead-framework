<?php

namespace Equit\Responses;

trait DoesntHaveContent
{
	public function contentType(): string
	{
		return "";
	}

	public function content(): string
	{
		return "";
	}
}
