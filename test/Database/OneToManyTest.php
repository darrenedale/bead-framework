<?php

namespace Database;

use Bead\Application;
use Bead\Database\Connection;
use Bead\Database\Model;
use Bead\Database\OneToMany;
use BeadTests\Framework\TestCase;
use Mockery;

class OneToManyTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private Model $local;

    private OneToMany $relation;

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

        /** @psalm-suppress UndefinedClass Bar is just a test class name */
        $this->relation = new OneToMany($this->local, "Bar", "foo_id", "id");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    public function testConstructor(): void
    {
        /** @psalm-suppress UndefinedClass Bar is just a test class name */
        $relation = new OneToMany($this->local, "Bar", "foo_id", "id");
        self::assertSame($this->local, $relation->localModel());
        self::assertEquals("Bar", $this->relation->relatedModel());
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("foo_id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        self::assertSame("id", $this->relation->localKey());
    }

    public function testLocalModel(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    public function testRelatedKey(): void
    {
        self::assertEquals("foo_id", $this->relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        self::assertEquals("Bar", $this->relation->relatedModel());
    }
}
