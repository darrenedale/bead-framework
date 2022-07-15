<?php

namespace Equit\Responses;

trait CanSetContentType
{
	private string $m_contentType = "application/octet-stream";

	public function setContentType(string $type): void
	{
		// TODO validation?
		$this->m_contentType = $type;
	}

	public function contentType(): string
	{
		return $this->m_contentType;
	}
}