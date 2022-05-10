<?php

namespace Equit\Util;

class Days implements TimeStampInterval
{
    private int $m_days;

    public function __construct(int $days)
	{
        $this->m_days = $days;
    }

    public function inSeconds(): int
	{
        return $this->m_days * 60 * 60 * 24;
    }
}
