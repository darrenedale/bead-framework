<?php

declare(strict_types=1);

namespace Bead\Contracts;

/** Contract for classes representing feature flags. */
interface FeatureFlag
{
    /** @return string The name of the feature. */
    public function feature(): string;

    /** @return string|null The feature's variant, if there are different implementations of the feature. */
    public function variant(): ?string;
}
