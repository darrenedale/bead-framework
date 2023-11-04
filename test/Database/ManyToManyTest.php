<?php

namespace Database;

use Bead\Application;
use Bead\Database\Connection;
use Bead\Database\ManyToMany;
use BeadTests\Framework\TestCase;
use Bead\Database\Model;
use Mockery;

class ManyToManyTest extends TestCase
{
    private Application $app;

    private Connection $db;

    private Model $local;

    private ManyToMany $relation;

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

        $this->relation = new ManyToMany($this->local, "Bar", "FooBarLink", "foo_id", "bar_id", "pk_on_foo", "pk_on_bar");
    }

    public function tearDown(): void
    {
        unset($this->relation, $this->app, $this->db, $this->local);
        parent::tearDown();
    }

    public function testConstructorDefaults(): void
    {
        $relation = new ManyToMany($this->local, "Bar", "FooBarLink", "foo_id", "bar_id");
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    public function testConstructorWithLocalKey(): void
    {
        $relation = new ManyToMany($this->local, "Bar", "FooBarLink", "foo_id", "bar_id", "the_id");
        self::assertEquals("the_id", $relation->localKey());
        self::assertEquals("id", $relation->relatedKey());
    }

    public function testConstructorWithRelatedKey(): void
    {
        $relation = new ManyToMany($this->local, "Bar", "FooBarLink", "foo_id", "bar_id", null, "the_related_id");
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("the_related_id", $relation->relatedKey());
    }

    public function testConstructorWithLocalAndRelatedKey(): void
    {
        $relation = new ManyToMany($this->local, "Bar", "FooBarLink", "foo_id", "bar_id", "the_local_id", "the_related_id");
        self::assertEquals("the_local_id", $relation->localKey());
        self::assertEquals("the_related_id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        self::assertSame("pk_on_foo", $this->relation->localKey());
    }

    public function testLocalModel(): void
    {
        self::assertSame($this->local, $this->relation->localModel());
    }

    public function testPivotLocalKey(): void
    {
        self::assertEquals("foo_id", $this->relation->pivotLocalKey());
    }

    public function testPivotRelatedKey(): void
    {
        self::assertEquals("bar_id", $this->relation->pivotRelatedKey());
    }

    public function testPivotModel(): void
    {
        self::assertEquals("FooBarLink", $this->relation->pivotModel());
    }

    public function testRelatedKey(): void
    {
        self::assertEquals("pk_on_bar", $this->relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        self::assertEquals("Bar", $this->relation->relatedModel());
    }
}
