<?php

namespace Equit\Util;

class Hours implements TimeStampInterval
{
    private int $m_hours;

    public function __construct(int $hours)
	{
        $this->m_hours = $hours;
    }

    public function inSeconds(): int
	{
        return $this->m_hours * 60 * 60;
    }
}
