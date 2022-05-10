<?php

namespace Equit\Util;

class Minutes implements TimeStampInterval
{
    private int $m_mins;

    public function __construct(int $mins)
	{
        $this->m_mins = $mins;
    }

    public function inSeconds(): int
	{
        return $this->m_mins * 60;
    }
}
