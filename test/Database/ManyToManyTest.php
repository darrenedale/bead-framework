<?php

namespace Database;

use Bead\Core\Application;
use Bead\Database\Connection;
use Bead\Database\ManyToMany;
use Bead\Database\Model;
use BeadTests\Database\Models\ModelA;
use BeadTests\Database\Models\ModelABLink;
use BeadTests\Database\Models\ModelB;
use BeadTests\Framework\TestCase;
use Mockery;

class ManyToManyTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private ModelA $local;

    private ManyToMany $relation;

    public function setUp(): void
    {
        $this->db = Mockery::mock(Connection::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("database")->andReturn($this->db);

        $this->local = new ModelA();
        $this->relation = new ManyToMany($this->local, ModelB::class, ModelABLink::class, "a_id", "b_id", "pk_on_a", "pk_on_b");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    /** Ensure the constructor uses the expected defaults. */
    public function testConstructor1(): void
    {
        $relation = new ManyToMany($this->local, ModelB::class, ModelABLink::class, "a_id", "b_id");
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    /** Ensure the local key can be set in the constructor. */
    public function testConstructor2(): void
    {
        $relation = new ManyToMany($this->local, ModelB::class, ModelABLink::class, "a_id", "b_id", "the_id");
        self::assertEquals("the_id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    /** Ensure the related key can be set in the constructor. */
    public function testConstructor3(): void
    {
        $relation = new ManyToMany($this->local, ModelB::class, ModelABLink::class, "a_id", "b_id", null, "the_related_id");
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("the_related_id", $relation->relatedKey());
    }

    /** Ensure both the local and related keys can be set in the constructor. */
    public function testConstructor4(): void
    {
        $relation = new ManyToMany($this->local, ModelB::class, ModelABLink::class, "a_id", "b_id", "the_local_id", "the_related_id");
        self::assertEquals("the_local_id", $relation->localKey());
        self::assertEquals("the_related_id", $relation->relatedKey());
    }

    /** Ensure we can fetch the local key. */
    public function testLocalKey1(): void
    {
        self::assertSame("pk_on_a", $this->relation->localKey());
    }

    /** Ensure we can fetch the local model. */
    public function testLocalModel1(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    /** Ensure we can fetch the name of the column in the pivot table associated with the local model. */
    public function testPivotLocalKey1(): void
    {
        self::assertEquals("a_id", $this->relation->pivotLocalKey());
    }

    /** Ensure we can fetch the name of the column in the pivot table associated with the related model. */
    public function testPivotRelatedKey1(): void
    {
        self::assertEquals("b_id", $this->relation->pivotRelatedKey());
    }

    /** Ensure we can fetch the pivot model class name. */
    public function testPivotModel(): void
    {
        self::assertEquals(ModelABLink::class, $this->relation->pivotModel());
    }

    /** Ensure we can fetch the name of the column in the related table associated with the pivot model. */
    public function testRelatedKey(): void
    {
        self::assertEquals("pk_on_b", $this->relation->relatedKey());
    }

    /** Ensure we can fetch the related model class name. */
    public function testRelatedModel(): void
    {
        self::assertEquals(ModelB::class, $this->relation->relatedModel());
    }
}
