<?php

declare(strict_types=1);

namespace BeadTests\Core;

use Bead\Core\FeatureFlag;
use BeadTests\Framework\TestCase;

class FeatureFlagTest extends TestCase
{
    private const FeatureName = "bead-feature-1";

    private const Variant = "variant-1";

    private FeatureFlag $m_testFlag;

    public function setUp(): void
    {
        $this->m_testFlag = new FeatureFlag(self::FeatureName, self::Variant);
    }

    public function tearDown(): void
    {
        unset($this->m_testFlag);
        parent::tearDown();
    }

    /** Ensure the constrcutor sets the feature. */
    public function testConstructor1(): void
    {
        $flag = new FeatureFlag("bead-feature-2");
        self::assertEquals("bead-feature-2", $flag->feature());
    }

    /** Ensure the variant is null by default. */
    public function testConstructor2(): void
    {
        $flag = new FeatureFlag("bead-feature-2");
        self::assertNull($flag->variant());
    }

    /** Ensure the variant can be set. */
    public function testConstructor3(): void
    {
        $flag = new FeatureFlag("bead-feature-2", "the-variant");
        self::assertEquals("the-variant", $flag->variant());
    }

    /** Ensure we get the expected feature name. */
    public function testFeature1(): void
    {
        self::assertEquals(self::FeatureName, $this->m_testFlag->feature());
    }

    /** Ensure we can set the feature immutably. */
    public function testWithFeature1(): void
    {
        $flag = $this->m_testFlag->withFeature("test-feature-2");
        self::assertEquals("test-feature-2", $flag->feature());
        self::assertNotSame($this->m_testFlag, $flag);
        self::assertEquals(self::FeatureName, $this->m_testFlag->feature());
    }

    /** Ensure we get the expected variant name. */
    public function testVariant1(): void
    {
        self::assertEquals(self::Variant, $this->m_testFlag->variant());
    }

    /** Ensure we can set the variant immutably. */
    public function testWithVariant1(): void
    {
        $flag = $this->m_testFlag->withVariant("variant-2");
        self::assertEquals("variant-2", $flag->variant());
        self::assertNotSame($this->m_testFlag, $flag);
        self::assertEquals(self::Variant, $this->m_testFlag->variant());
    }

    /** Ensure we can set the variant to null immutably. */
    public function testWithVariant2(): void
    {
        $flag = $this->m_testFlag->withVariant(null);
        self::assertNull($flag->variant());
        self::assertNotSame($this->m_testFlag, $flag);
        self::assertEquals(self::Variant, $this->m_testFlag->variant());
    }
}
