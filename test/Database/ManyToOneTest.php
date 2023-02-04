<?php

namespace Database;

use BeadTests\Framework\TestCase;
use Bead\Application;
use Bead\Database\Connection;
use Bead\Database\ManyToOne;
use Bead\Database\Model;
use Mockery;

class ManyToOneTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private Model $local;

    private ManyToOne $relation;

    public function setUp(): void
    {
        $this->db = Mockery::mock(Connection::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("database")->andReturn($this->db);
        $this->local = new class extends Model
        {
            protected static string $table = "Foo";
        };

        $this->relation = new ManyToOne($this->local, "Bar", "id", "bar_id");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        $relation = new ManyToOne($this->local, "Bar", "id", "bar_id");
        self::assertSame($this->local, $relation->localModel());
        self::assertEquals("Bar", $this->relation->relatedModel());
        self::assertEquals("bar_id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        self::assertSame("bar_id", $this->relation->localKey());
    }

    public function testLocalModel(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    public function testRelatedKey(): void
    {
        self::assertEquals("id", $this->relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        self::assertEquals("Bar", $this->relation->relatedModel());
    }
}
