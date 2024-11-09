<?php

namespace Database;

use Bead\Core\Application;
use Bead\Database\Connection;
use Bead\Database\ManyToOne;
use Bead\Database\Model;
use BeadTests\Database\Models\ModelA;
use BeadTests\Database\Models\ModelB;
use BeadTests\Framework\TestCase;
use Mockery;

class ManyToOneTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private ModelA $local;

    private ManyToOne $relation;

    public function setUp(): void
    {
        $this->db = Mockery::mock(Connection::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("database")->andReturn($this->db);

        $this->local = new ModelA();
        $this->relation = new ManyToOne($this->local, ModelB::class, "id", "b_id");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    /** Ensure we can set the related model and tbe associated columns in the construactor. */
    public function testConstructor1(): void
    {
        $relation = new ManyToOne($this->local, ModelB::class, "id", "b_id");
        self::assertSame($this->local, $relation->localModel());
        self::assertEquals(ModelB::class, $this->relation->relatedModel());
        self::assertEquals("b_id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    /** Ensure we can retrieve the name of the local column associated with the related model. */
    public function testLocalKey1(): void
    {
        self::assertSame("b_id", $this->relation->localKey());
    }

    /** Ensure we can retrieve the local model that owns the association. */
    public function testLocalModel1(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    /** Ensure we can retrieve the name of the associated column on the related model. */
    public function testRelatedKey1(): void
    {
        self::assertEquals("id", $this->relation->relatedKey());
    }

    /** Ensure we can retrieve the name of the related model class. */
    public function testRelatedModel1(): void
    {
        self::assertEquals(ModelB::class, $this->relation->relatedModel());
    }
}
