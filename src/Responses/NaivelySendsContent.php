<?php

namespace Equit\Responses;

trait NaivelySendsContent
{
	use SendsHeaders;

	abstract public function statusCode(): int;
	abstract public function contentType(): string;
	abstract public function content(): string;

	public function send(): void
	{
		http_response_code($this->statusCode());
		header("content-type", $this->contentType());

		foreach ($this->headers() as $header => $value) {
			header($header, $value, true);
		}

		echo $this->content();
	}
}
