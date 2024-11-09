<?php

namespace Database;

use Bead\Core\Application;
use Bead\Database\Connection;
use Bead\Database\Model;
use Bead\Database\OneToMany;
use BeadTests\Database\Models\ModelA;
use BeadTests\Database\Models\ModelB;
use BeadTests\Framework\TestCase;
use Mockery;

class OneToManyTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private ModelA $local;

    private OneToMany $relation;

    public function setUp(): void
    {
        $this->db = Mockery::mock(Connection::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("database")->andReturn($this->db);

        $this->local = new ModelA();
        $this->relation = new OneToMany($this->local, ModelB::class, "a_id", "id");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    /** Ensure we can set the related model and tbe associated columns in the construactor. */
    public function testConstructor1(): void
    {
        $relation = new OneToMany($this->local, ModelB::class, "a_id", "id");
        self::assertSame($this->local, $relation->localModel());
        self::assertEquals(ModelB::class, $this->relation->relatedModel());
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("a_id", $relation->relatedKey());
    }

    /** Ensure we can retrieve the name of the local column associated with the related model. */
    public function testLocalKey1(): void
    {
        self::assertSame("id", $this->relation->localKey());
    }

    /** Ensure we can retrieve the local model that owns the association. */
    public function testLocalModel1(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    /** Ensure we can retrieve the name of the associated column on the related model. */
    public function testRelatedKey1(): void
    {
        self::assertEquals("a_id", $this->relation->relatedKey());
    }

    /** Ensure we can retrieve the name of the related model class. */
    public function testRelatedModel1(): void
    {
        self::assertEquals(ModelB::class, $this->relation->relatedModel());
    }
}
