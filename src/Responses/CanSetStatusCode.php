<?php

namespace Equit\Responses;

trait CanSetStatusCode
{
	private int $m_statusCode;

	public function statusCode(): int
	{
		return $this->m_statusCode;
	}

	public function setStatusCode(int $code): void
	{
		$this->m_statusCode = $code;
	}
}
