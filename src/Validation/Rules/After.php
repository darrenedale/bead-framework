<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use DateTime;
use Bead\Validation\Rule;
use Throwable;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that a date is after a given date.
 */
class After implements Rule
{
    /** @var DateTime The date that the data must be after. */
    private DateTime $m_dateTime;

    /**
     * Initialise a new rule instance.
     *
     * @param DateTime $dateTime The date that the data must be after.
     */
    public function __construct(DateTime $dateTime)
    {
        $this->setDateTime($dateTime);
    }

    /**
     * Fetch the date that the data must be after.
     *
     * @return DateTime The date.
     */
    public function dateTime(): DateTime
    {
        return $this->m_dateTime;
    }

    /**
     * Set the date that the data must be after.
     *
     * @param DateTime $dateTime The date.
     */
    public function setDateTime(DateTime $dateTime): void
    {
        $this->m_dateTime = $dateTime;
    }

    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is after the given date, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        if (!($data instanceof DateTime)) {
            try {
                $data = new DateTime($data);
            } catch (Throwable $err) {
                return false;
            }
        }

        return $this->dateTime() < $data;
    }

    /**
     * Fetch the default message for when the data does not pass the rule.
     *
     * @param string $field The field under validation.
     *
     * @return string The message.
     */
    public function message(string $field): string
    {
        return tr("The field %1 must be before %2", __FILE__, __LINE__, $this->dateTime()->format("Y-m-d H:i:s"));
    }
}
