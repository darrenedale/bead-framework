<?php

namespace Equit\Validation\Rules;

use DateTime;
use Equit\Validation\Rule;
use Throwable;

class After implements Rule
{
    private DateTime $m_dateTime;

    public function __construct(DateTime $dateTime)
    {
        $this->setDateTime($dateTime);
    }

    public function dateTime(): DateTime
    {
        return $this->m_dateTime;
    }

    public function setDateTime(DateTime $dateTime): void
    {
        $this->m_dateTime = $dateTime;
    }

    public function passes(string $field, $data): bool
    {
        if (!($data instanceof DateTime)) {
            try {
                $data = new DateTime($data);
            }
            catch (Throwable $err) {
                return false;
            }
        }

        return $this->dateTime() < $data;
    }

    public function message(string $field): string
    {
        return tr("The field %1 must be before %2", __FILE__, __LINE__, $this->dateTime()->format("Y-m-d H:i:s"));
    }
}
