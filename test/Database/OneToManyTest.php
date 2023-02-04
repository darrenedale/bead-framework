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
    private Application $m_app;
    private Connection $m_db;
    private Model $m_local;
    private OneToMany $m_relation;

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

        $this->m_relation = new OneToMany($this->m_local, "Bar", "foo_id", "id");
    }

    public function tearDown(): void
    {
        uopz_unset_return(Application::class, "instance");
        unset($this->m_relation, $this->m_app, $this->m_db, $this->m_local);
    }

    public function testConstructor(): void
    {
        $relation = new OneToMany($this->m_local, "Bar", "foo_id", "id");
        self::assertSame($this->m_local, $relation->localModel());
        self::assertEquals("Bar", $this->m_relation->relatedModel());
        self::assertEquals("id", $relation->localKey());
        self::assertEquals("foo_id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        self::assertSame("id", $this->m_relation->localKey());
    }

    public function testLocalModel(): void
    {
        self::assertSame($this->m_local, $this->m_relation->localModel());
    }

    public function testRelatedKey(): void
    {
        self::assertEquals("foo_id", $this->m_relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        self::assertEquals("Bar", $this->m_relation->relatedModel());
    }
}
