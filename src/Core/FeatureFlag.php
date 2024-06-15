<?php

declare(strict_types=1);

namespace Bead\Core;

use Bead\Contracts\FeatureFlag as FeatureFlagContract;

/**
 * Lightweight representation of a feature flag.
 *
 * Instances of this class model immutability.
 */
class FeatureFlag implements FeatureFlagContract
{
    /** @var string The name of the feature. */
    private string $feature;

    /** @var string|null The variant of the feature, if applicable. */
    private ?string $variant;

    /**
     * @param string $feature The name of the feature.
     * @param string|null $variant The variant, if applicable.
     */
    public function __construct(string $feature, ?string $variant = null)
    {
        $this->feature = $feature;
        $this->variant = $variant;
    }

    /** @return string The feature name. */
    public function feature(): string
    {
        return $this->feature;
    }

    /**
     * Create a copy of the FeatureFlag with a different feature name.
     *
     * @param string $feature The name of the feature.
     *
     * @return self A clone of the FeatureFlag with the given name.
     */
    public function withFeature(string $feature): self
    {
        $clone = clone $this;
        $clone->feature = $feature;
        return $clone;
    }

    /** @return string|null The feature variant. */
    public function variant(): ?string
    {
        return $this->variant;
    }

    /**
     * Create a copy of the FeatureFlag with a different variant.
     *
     * @param string|null $variant The variant.
     *
     * @return self A clone of the FeatureFlag with the given variant.
     */
    public function withVariant(?string $variant): self
    {
        $clone = clone $this;
        $clone->variant = $variant;
        return $clone;
    }
}
