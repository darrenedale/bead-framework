<?php

namespace BeadTests\Session;

use Bead\Session\DataAccessor;
use Bead\Session\PrefixedAccessor;
use BeadTests\Framework\TestCase;
use Equit\XRay\XRay;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;

/** @covers \Bead\Session\PrefixedAccessor */
class PrefixedAccessorTest extends TestCase
{
    /** @var DataAccessor&MockInterface  */
    private DataAccessor $m_parent;

    private PrefixedAccessor $m_prefixedAccessor;

    protected function setUp(): void
    {
        $this->m_parent = Mockery::mock(DataAccessor::class);
        $this->m_prefixedAccessor = new PrefixedAccessor("test-prefix.", $this->m_parent);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        unset($this->m_parent, $this->m_prefixedAccessor);
    }

    /** Ensure the constructor uses the provided prefix. */
    public function testConstructor1(): void
    {
        $accessor = new XRay(new PrefixedAccessor("test-prefix", $this->m_parent));
        self::assertSame("test-prefix", $accessor->prefixedKey(""));
    }

    /** Ensure the constructor uses the provided parent accessor. */
    public function testConstructor2(): void
    {
        $accessor = new XRay(new PrefixedAccessor("test-prefix", $this->m_parent));
        self::assertSame($this->m_parent, $accessor->m_parent);
    }

    /** Ensure has() correctly reports a key that exists. */
    public function testHas1(): void
    {
        $this->m_parent->expects("has")
            ->once()
            ->with("test-prefix.key-name")
            ->andReturn(true);

        self::assertTrue($this->m_prefixedAccessor->has("key-name"));
    }

    /** Ensure has() correctly reports a key that doesn't exist. */
    public function testHas2(): void
    {
        $this->m_parent->expects("has")
            ->once()
            ->with("test-prefix.key-name")
            ->andReturn(false);

        self::assertFalse($this->m_prefixedAccessor->has("key-name"));
    }

    /** Ensure get() returns the correct value from the parent accessor. */
    public function testGet1(): void
    {
        $this->m_parent->expects("get")
            ->once()
            ->with("test-prefix.key-name", null)
            ->andReturn("test-value");

        self::assertSame("test-value", $this->m_prefixedAccessor->get("key-name"));
    }

    /** Ensure get() passes on the default to the parent accessor. */
    public function testGet2(): void
    {
        $this->m_parent->expects("get")
            ->once()
            ->with("test-prefix.key-name", "default-value")
            ->andReturn("default-value");

        self::assertSame("default-value", $this->m_prefixedAccessor->get("key-name", "default-value"));
    }

    /** Ensure a single key can is extracted correctly from the parent accessor. */
    public function testExtract1(): void
    {
        $this->m_parent->expects("extract")
            ->once()
            ->with("test-prefix.test-key")
            ->andReturn("test-value");

        self::assertSame("test-value", $this->m_prefixedAccessor->extract("test-key"));
    }

    /** Ensure the correct exception is thrown when a non-string key is provided to extract(). */
    public function testExtract2(): void
    {
        $this->m_parent->expects("extract")
            ->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Keys for session data must be strings");
        $this->m_prefixedAccessor->extract(["test-key", 42,]);
    }

    /**
     * Ensure the parent accessor is asked to extract all the correct keys and the prefixed accessor correctly strips
     * the prefix from the returned keys.
     */
    public function testExtract3(): void
    {
        $this->m_parent->expects("extract")
            ->once()
            ->with(["test-prefix.test-key", "test-prefix.other-test-key",])
            ->andReturn(["test-prefix.test-key" => "test-value-1", "test-prefix.other-test-key" => "test-value-2",]);

        self::assertSame(
            ["test-key" => "test-value-1", "other-test-key" => "test-value-2",],
            $this->m_prefixedAccessor->extract(["test-key", "other-test-key",]),
        );
    }

    /** Ensure setting a single value is correctly forwarded to the parent accessor with the prefix. */
    public function testSet1(): void
    {
        $this->m_parent->expects("set")
            ->once()
            ->with("test-prefix.test-key", "test-value");

        $this->m_prefixedAccessor->set("test-key", "test-value");
        self::markTestAsExternallyVerified();
    }

    /** Ensure setting multiple values correctly forwards to the parent accessor with the prefix added to all keys. */
    public function testSet2(): void
    {
        $this->m_parent->expects("set")
            ->once()
            ->with("test-prefix.test-key", "test-value-1");

        $this->m_parent->expects("set")
            ->once()
            ->with("test-prefix.other-test-key", "test-value-2");

        $this->m_prefixedAccessor->set([
            "test-key" => "test-value-1",
            "other-test-key" => "test-value-2",
        ]);

        self::markTestAsExternallyVerified();
    }

    /** Ensure the prefixed accessor pushes the correct value to the parent accessor. */
    public function testPush1(): void
    {
        $this->m_parent->expects("push")
            ->once()
            ->with("test-prefix.test-key", "test-value");

        $this->m_prefixedAccessor->push("test-key", "test-value");
        self::markTestAsExternallyVerified();
    }

    /** Ensure the prefixed accessor pushes all the correct values to the parent accessor. */
    public function testPushAll1(): void
    {
        $this->m_parent->expects("pushAll")
            ->once()
            ->with("test-prefix.test-key", ["one", "two", "three",]);

        $this->m_prefixedAccessor->pushAll("test-key", ["one", "two", "three",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure pop pops the correct number of values from the parent accessor. */
    public function testPop1(): void
    {
        $this->m_parent->expects("pop")
            ->once()
            ->with("test-prefix.test-key", 3)
            ->andReturn(["one", "two", "three",]);

        self::assertSame(["one", "two", "three",], $this->m_prefixedAccessor->pop("test-key", 3));
    }

    /** Ensure pop pops one values from the parent accessor by default. */
    public function testPop2(): void
    {
        $this->m_parent->expects("pop")
            ->once()
            ->with("test-prefix.test-key", 1)
            ->andReturn(["one",]);

        self::assertSame(["one",], $this->m_prefixedAccessor->pop("test-key"));
    }

    /** Ensure a single key can be trasiently set. */
    public function testTransientSet1(): void
    {
        $this->m_parent->expects("transientSet")
            ->once()
            ->with("test-prefix.test-key", "test-value");

        $this->m_prefixedAccessor->transientSet("test-key", "test-value");
        self::markTestAsExternallyVerified();
    }

    /** Ensure invalid keys are detected when transiently setting data. */
    public function testTransientSet2(): void
    {
        $this->m_parent->expects("transientSet")
            ->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Keys for session data must be strings");
        $this->m_prefixedAccessor->transientSet([
            "test-key" => "test-value-1",
            42 => "test-value-2",
        ]);
    }

    /** Ensure transiently setting multiple keys is correctly forwarded to the parent accessor. */
    public function testTransientSet3(): void
    {
        $this->m_parent->expects("transientSet")
            ->once()
            ->with("test-prefix.test-key", "test-value-1");

        $this->m_parent->expects("transientSet")
            ->once()
            ->with("test-prefix.other-test-key", "test-value-2");

        $this->m_prefixedAccessor->transientSet([
            "test-key" => "test-value-1",
            "other-test-key" => "test-value-2",
        ]);

        self::markTestAsExternallyVerified();
    }

    /** Ensure single-key removal is correctly forwarded to the parent accessor. */
    public function testRemove1(): void
    {
        $this->m_parent->expects("remove")
            ->once()
            ->with("test-prefix.test-key");

        $this->m_prefixedAccessor->remove("test-key");
        self::markTestAsExternallyVerified();
    }

    /** Ensure the correct exception is thrown when a non-string key is provided to remomve(). */
    public function testRemove2(): void
    {
        $this->m_parent->expects("remove")
            ->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Keys for session data must be strings");
        $this->m_prefixedAccessor->remove(["test-key", 42,]);
    }

    /** Ensure multiple-key removal is correctly forwarded to the parent accessor. */
    public function testRemove3(): void
    {
        $this->m_parent->expects("remove")
            ->once()
            ->with("test-prefix.test-key");

        $this->m_parent->expects("remove")
            ->once()
            ->with("test-prefix.other-test-key");

        $this->m_prefixedAccessor->remove(["test-key", "other-test-key",]);
        self::markTestAsExternallyVerified();
    }

    /** Ensure all() correctly filters the keys from the parent accessor. */
    public function testAll1(): void
    {
        $this->m_parent->expects("all")
            ->once()
            ->withNoArgs()
            ->andReturn([
                "tst-prefix.test-key" => "absent-value",
                "different-prefix.test-key" => "absent-value",
                "test-prefix.test-key" => "present-value-1",
                "test-key" => "no-prefix-absent-value",
                "test-prefix.other-test-key" => "present-value-2",
            ]);

        self::assertSame(
            [
                "test-key" => "present-value-1",
                "other-test-key" => "present-value-2",
            ],
            $this->m_prefixedAccessor->all(),
        );
    }
}
