<?php

namespace Database;

use Equit\Application;
use Equit\Database\Connection;
use Equit\Database\ManyToMany;
use BeadTests\Framework\TestCase;
use Equit\Database\Model;
use Mockery;

use function uopz_set_return;
use function uopz_clear_return;

class ManyToManyTest extends TestCase
{
    private Application $m_app;
    private Connection $m_db;
    private Model $m_local;
    private ManyToMany $m_relation;

    public function setUp(): void
    {
        $this->m_db = Mockery::mock(Connection::class);
        $this->m_app = Mockery::mock(Application::class);
        uopz_set_return(Application::class, "instance", $this->m_app);

        $this->m_app->shouldReceive("database")->andReturn($this->m_db);
        $this->m_local = new class extends Model
        {
            protected static string $table = "Foo";
        };

        $this->m_relation = new ManyToMany($this->m_local, "Bar", "FooBarLink", "foo_id", "bar_id", "pk_on_foo", "pk_on_bar");
    }

    public function tearDown(): void
    {
        uopz_unset_return(Application::class, "instance");
        unset($this->m_relation, $this->m_app, $this->m_db, $this->m_local);
    }

    public function testConstructorDefaults(): void
    {
        $relation = new ManyToMany($this->m_local, "Bar", "FooBarLink", "foo_id", "bar_id");
        $this->assertEquals("id", $relation->localKey());
        $this->assertEquals("id", $relation->relatedKey());
    }

    public function testConstructorWithLocalKey(): void
    {
        $relation = new ManyToMany($this->m_local, "Bar", "FooBarLink", "foo_id", "bar_id", "the_id");
        $this->assertEquals("the_id", $relation->localKey());
        $this->assertEquals("id", $relation->relatedKey());
    }

    public function testConstructorWithRelatedKey(): void
    {
        $relation = new ManyToMany($this->m_local, "Bar", "FooBarLink", "foo_id", "bar_id", null, "the_related_id");
        $this->assertEquals("id", $relation->localKey());
        $this->assertEquals("the_related_id", $relation->relatedKey());
    }

    public function testConstructorWithLocalAndRelatedKey(): void
    {
        $relation = new ManyToMany($this->m_local, "Bar", "FooBarLink", "foo_id", "bar_id", "the_local_id", "the_related_id");
        $this->assertEquals("the_local_id", $relation->localKey());
        $this->assertEquals("the_related_id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        $this->assertSame("pk_on_foo", $this->m_relation->localKey());
    }

    public function testLocalModel(): void
    {
        $this->assertSame($this->m_local, $this->m_relation->localModel());
    }

    public function testPivotLocalKey(): void
    {
        $this->assertEquals("foo_id", $this->m_relation->pivotLocalKey());
    }

    public function testPivotRelatedKey(): void
    {
        $this->assertEquals("bar_id", $this->m_relation->pivotRelatedKey());
    }

    public function testPivotModel(): void
    {
        $this->assertEquals("FooBarLink", $this->m_relation->pivotModel());
    }

    public function testRelatedKey(): void
    {
        $this->assertEquals("pk_on_bar", $this->m_relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        $this->assertEquals("Bar", $this->m_relation->relatedModel());
    }
}
