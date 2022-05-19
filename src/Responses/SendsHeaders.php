<?php

namespace Equit\Responses;

trait SendsHeaders
{
	public abstract function headers(): array;

	protected function sendHeaders(): void
	{
		foreach ($this->headers() as $header => $value) {
			header("{$header}: {$value}", true);
		}

		header("content-type: {$this->contentType()}", true);
	}
}
