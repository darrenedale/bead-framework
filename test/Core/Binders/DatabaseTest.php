<?php

namespace BeadTests\Core\Binders;

use Bead\Core\Application;
use Bead\Core\Binders\Database as DatabaseBinder;
use Bead\Database\Connection;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Testing\StaticXRay;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use PDOException;

use function array_keys;

/** Test the bundled database binder. */
final class DatabaseTest extends TestCase
{
    private DatabaseBinder $database;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        $this->database = new DatabaseBinder();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->database, $this->app);
        parent::tearDown();
    }

    /** Ensure we get the expected config array. */
    public function testDriverConfig1(): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        $config = [
            "mysql" => [
                "host" => "localhost",
                "name" => "bead",
                "user" => "bead-mysql-user",
                "password" => "bead-mysql-password",
            ],
            "postgres" => [
                "host" => "pg-localhost",
                "name" => "pg-bead",
                "user" => "bead-postgres-user",
                "password" => "bead-postgres-password",
            ],
        ];

        self::assertEquals($config["mysql"], $database->driverConfig("mysql", $config));
    }

    /** Ensure driverConfig() throws when the driver's config is missing. */
    public function testDriverConfig2(): void
    {
        $database = new StaticXRay(DatabaseBinder::class);

        $config = [
            "postgres" => [
                "host" => "pg-localhost",
                "name" => "pg-bead",
                "user" => "bead-postgres-user",
                "password" => "bead-postgres-password",
            ],
        ];

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected database driver configuration for mysql, no configuration found");
        $database->driverConfig("mysql", $config);
    }

    /** Ensure driverConfig() throws when the driver's config is not an array. */
    public function testDriverConfig3(): void
    {
        $database = new StaticXRay(DatabaseBinder::class);

        $config = [
            "mysql" => "mysql:host=localhost;dbname=bead",
            "postgres" => [
                "host" => "pg-localhost",
                "name" => "pg-bead",
                "user" => "bead-postgres-user",
                "password" => "bead-postgres-password",
            ],
        ];

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected database driver configuration for mysql, found string");
        $database->driverConfig("mysql", $config);
    }

    public static function dataForTestMySqlDsn1(): iterable
    {
        yield "minimal hostname" => [
            [
                "host" => "localhost",
                "name" => "bead_db",
            ],
            "mysql:dbname=bead_db;host=localhost"
        ];

        yield "socket" => [
            [
                "socket" => "/var/run/mysql.sock",
                "name" => "bead_db",
            ],
            "mysql:dbname=bead_db;unix_socket=/var/run/mysql.sock"
        ];

        yield "hostname and port" => [
            [
                "host" => "localhost",
                "port" => "3307",
                "name" => "bead_db",
            ],
            "mysql:dbname=bead_db;host=localhost;port=3307"
        ];

        yield "different hostname and port" => [
            [
                "host" => "db.bead.com",
                "port" => "3309",
                "name" => "the_bead_db",
            ],
            "mysql:dbname=the_bead_db;host=db.bead.com;port=3309"
        ];

        yield "different socket" => [
            [
                "socket" => "/var/run/bead-mysql.sock",
                "name" => "the_bead_db",
            ],
            "mysql:dbname=the_bead_db;unix_socket=/var/run/bead-mysql.sock"
        ];
    }

    /**
     * @dataProvider dataForTestMySqlDsn1
     * @param array $config The MySQL database config.
     * @param string $expectedDsn The DSN that should be generated.
     */
    public function testMySqlDsn1(array $config, string $expectedDsn): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        self::assertEquals($expectedDsn, $database->mySqlDsn($config));
    }

    public static function dataForTestPgSqlDsn1(): iterable
    {
        yield "minimal hostname" => [
            [
                "host" => "localhost",
                "name" => "bead_db",
            ],
            "pgsql:dbname=bead_db;host=localhost"
        ];

        yield "hostname and port" => [
            [
                "host" => "localhost",
                "port" => "9099",
                "name" => "bead_db",
            ],
            "pgsql:dbname=bead_db;host=localhost;port=9099"
        ];

        yield "hostname and ssl mode" => [
            [
                "host" => "localhost",
                "sslmode" => "verify-full",
                "name" => "bead_db",
            ],
            "pgsql:dbname=bead_db;host=localhost;sslmode=verify-full"
        ];

        yield "hostname, port and ssl mode" => [
            [
                "host" => "localhost",
                "port" => "9012",
                "sslmode" => "verify-ca",
                "name" => "bead_db",
            ],
            "pgsql:dbname=bead_db;host=localhost;port=9012;sslmode=verify-ca"
        ];

        yield "different hostname, port and ssl mode" => [
            [
                "host" => "db.bead.com",
                "port" => "9091",
                "sslmode" => "require",
                "name" => "the_bead_db",
            ],
            "pgsql:dbname=the_bead_db;host=db.bead.com;port=9091;sslmode=require"
        ];
    }

    /**
     * @dataProvider dataForTestPgSqlDsn1
     * @param array $config The MsSQL database config.
     * @param string $expectedDsn The DSN that should be generated.
     */
    public function testPgSqlDsn1(array $config, string $expectedDsn): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        self::assertEquals($expectedDsn, $database->pgSqlDsn($config));
    }

    public static function dataForTestMsSqlDsn1(): iterable
    {
        yield "minimal hostname" => [
            [
                "host" => "localhost",
                "name" => "bead_db",
            ],
            "sqlsrv:Database=bead_db;Server=localhost"
        ];

        yield "hostname, database and tracing app" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 1,
                "app" => "bead-framework",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1;APP=bead-framework"
        ];

        yield "hostname, database and tracing WSID" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 1,
                "wsid" => "bead-id-1-2-3-4",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1;WSID=bead-id-1-2-3-4"
        ];

        yield "hostname, database and tracing file" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 1,
                "tracefile" => "/tmp/bead-ms-sql-tracefile",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1;TraceFile=/tmp/bead-ms-sql-tracefile"
        ];

        yield "different hostname and database" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com"
        ];

        yield "encryption enabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => true,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=1"
        ];

        yield "encryption disabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => false,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=0"
        ];

        yield "encryption enabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => 1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=1"
        ];

        yield "encryption enabled (other int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => 99,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=1"
        ];

        yield "encryption enabled (another int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => -1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=1"
        ];

        yield "encryption disabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => 0,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=0"
        ];

        yield "encryption enabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => "yes",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=1"
        ];

        yield "encryption disabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "encrypt" => "",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;Encrypt=0"
        ];

        yield "trust certificate enabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => true,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=1"
        ];

        yield "trust certificate disabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => false,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=0"
        ];

        yield "trust certificate enabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => 1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=1"
        ];

        yield "trust certificate enabled (other int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => 99,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=1"
        ];

        yield "trust certificate enabled (another int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => -1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=1"
        ];

        yield "trust certificate disabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => 0,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=0"
        ];

        yield "trust certificate enabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => "yes",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=1"
        ];

        yield "trust certificate disabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trust_cert" => "",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TrustServerCertificate=0"
        ];

        yield "tracing enabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => true,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1"
        ];

        yield "tracing disabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => false,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=0"
        ];

        yield "tracing enabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1"
        ];

        yield "tracing enabled (other int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 99,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1"
        ];

        yield "tracing enabled (another int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => -1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1"
        ];

        yield "tracing disabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => 0,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=0"
        ];

        yield "tracing enabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => "yes",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=1"
        ];

        yield "tracing disabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "trace" => "",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;TraceOn=0"
        ];

        yield "connection pooling enabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => true,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=1"
        ];

        yield "connection pooling disabled (bool)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => false,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=0"
        ];

        yield "connection pooling enabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => 1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=1"
        ];

        yield "connection pooling enabled (other int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => 99,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=1"
        ];

        yield "connection pooling enabled (another int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => -1,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=1"
        ];

        yield "connection pooling disabled (int)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => 0,
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=0"
        ];

        yield "connection pooling enabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => "yes",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=1"
        ];

        yield "connection pooling disabled (string)" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "pool" => "",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;ConnectionPooling=0"
        ];

        yield "timeout" => [
            [
                "host" => "db.bead.com",
                "name" => "the_bead_db",
                "timeout" => "30",
            ],
            "sqlsrv:Database=the_bead_db;Server=db.bead.com;LoginTimeout=30"
        ];
    }

    /**
     * @dataProvider dataForTestMsSqlDsn1
     * @param array $config The MsSQL database config.
     * @param string $expectedDsn The DSN that should be generated.
     */
    public function testMsSqlDsn1(array $config, string $expectedDsn): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        self::assertEquals($expectedDsn, $database->msSqlDsn($config));
    }

    public static function dataForTestDsn1(): iterable
    {
        yield "mysql" => [
            "mysql",
            [
                "host" => "localhost",
                "name" => "bead-mysql-db",
            ],
            "mysql:dbname=bead-mysql-db;host=localhost",
        ];

        yield "pgsql" => [
            "pgsql",
            [
                "host" => "localhost",
                "name" => "bead-pgsql-db",
            ],
            "pgsql:dbname=bead-pgsql-db;host=localhost",
        ];

        yield "mssql" => [
            "mssql",
            [
                "host" => "localhost",
                "name" => "bead-mssql-db",
            ],
            "sqlsrv:Database=bead-mssql-db;Server=localhost",
        ];
    }

    /**
     * @dataProvider dataForTestDsn1
     * @param array $config The database config to test with.
     * @param string $expectedDsn The DSN that should be generated.
     */
    public function testDsn1(string $driver, array $config, string $expectedDsn): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        self::assertEquals($expectedDsn, $database->dsn($driver, $config));
    }

    /** ensure dsn() throws when the driver is not recognised. */
    public function testDsn2(): void
    {
        $database = new StaticXRay(DatabaseBinder::class);
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting supported database driver, found \"unrecognised-driver\"");
        $database->dsn("unrecognised-driver", [
            "host" => "localhost",
            "name" => "bead-unrecognised-db",
        ]);
    }

    /** Ensure connection() attempts to create a database connection. */
    public function testConnection(): void
    {
        self::expectException(PDOException::class);
        $database = new StaticXRay(DatabaseBinder::class);
        $database->connection("", "", "");
    }

    public static function dataForTestBindServices1(): iterable
    {
        $config = [
            "mysql" => [
                "host" => "localhost",
                "name" => "bead-mysql-db",
                "user" => "bead-mysql-user",
                "password" => "bead-mysql-password",
            ],
            "pgsql" => [
                "host" => "pg.db.example.com",
                "name" => "bead-pg-db",
                "user" => "bead-pg-user",
                "password" => "bead-pg-password",
            ],
            "mssql" => [
                "host" => "sqlsrv-1.example.com",
                "name" => "bead-mssql-db",
                "user" => "bead-sqlsvr-user",
                "password" => "bead-sqlsvr-password",
            ],
        ];

        $dsns = [
            "mysql" => "mysql:dbname=bead-mysql-db;host=localhost",
            "pgsql" => "pgsql:dbname=bead-pg-db;host=pg.db.example.com",
            "mssql" => "sqlsrv:Database=bead-mssql-db;Server=sqlsrv-1.example.com",
        ];

        foreach (array_keys($dsns) as $driver) {
            $config["driver"] = $driver;
            yield $driver => [$config, $dsns[$driver],];
        }
    }

    /**
     * @dataProvider dataForTestBindServices1
     * @param array $config The db config to test with.
     * @param string $expectedDsn The DSN expected to be used to create the database connection.
     */
    public function testBindServices1(array $config, string $expectedDsn): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("db")
            ->andReturn($config);

        $connection = Mockery::mock(Connection::class);

        $this->mockMethod(
            DatabaseBinder::class,
            "connection",
            function (string $dsn, string $user, string $password) use ($expectedDsn, $config, $connection): Connection {
                DatabaseTest::assertEquals($expectedDsn, $dsn);
                $driver = $config["driver"];
                DatabaseTest::assertEquals($config[$driver]["user"], $user);
                DatabaseTest::assertEquals($config[$driver]["password"], $password);
                return $connection;
            }
        );

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(Connection::class, $connection);

        $this->database->bindServices($this->app);
    }

    /** ensure bindServices() throws when the driver is not given. */
    public function testBindServices2(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expecting valid driver, none found");

        $this->app->shouldReceive("config")
            ->once()
            ->with("db")
            ->andReturn([
                "mysql" => [
                    "host" => "localhost",
                    "name" => "bead-mysql-db",
                ],
                "pgsql" => [
                    "host" => "localhost",
                    "name" => "bead-pgsql-db",
                ],
                "mssql" => [
                    "host" => "localhost",
                    "name" => "bead-mssql-db",
                ],
            ]);
        $this->database->bindServices($this->app);
    }
}
