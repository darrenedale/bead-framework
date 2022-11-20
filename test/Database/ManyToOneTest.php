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
    private Application $m_app;
    private Connection $m_db;
    private Model $m_local;
    private ManyToOne $m_relation;

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

        $this->m_relation = new ManyToOne($this->m_local, "Bar", "id", "bar_id");
    }

    public function tearDown(): void
    {
        uopz_unset_return(Application::class, "instance");
        unset($this->m_relation, $this->m_app, $this->m_db, $this->m_local);
    }

    public function testConstructor(): void
    {
        $relation = new ManyToOne($this->m_local, "Bar", "id", "bar_id");
        $this->assertSame($this->m_local, $relation->localModel());
        $this->assertEquals("Bar", $this->m_relation->relatedModel());
        $this->assertEquals("bar_id", $relation->localKey());
        $this->assertEquals("id", $relation->relatedKey());
    }

    public function testLocalKey(): void
    {
        $this->assertSame("bar_id", $this->m_relation->localKey());
    }

    public function testLocalModel(): void
    {
        $this->assertSame($this->m_local, $this->m_relation->localModel());
    }

    public function testRelatedKey(): void
    {
        $this->assertEquals("id", $this->m_relation->relatedKey());
    }

    public function testRelatedModel(): void
    {
        $this->assertEquals("Bar", $this->m_relation->relatedModel());
    }
}
