<?php

namespace Equit\Responses;

trait DoesntHaveHeaders
{
	public function headers(): array
	{
		return [];
	}
}
