<?php

declare(strict_types=1);

namespace Bead\Encryption;

final class SerializationMode
{
    /** @var int Automatically detect whether (un)serialization is required. */
    public const Auto = 0;

    /** @var int Always attempt to (un)serialize the data. */
    public const On = 1;

    /** @var int Do not attempt to (un)serialize the data. */
    public const Off = 2;
}
