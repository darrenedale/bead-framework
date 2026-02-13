<?php

namespace BeadTests\Session\Handlers;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Core\Application;
use Bead\Exceptions\Session\InvalidSessionDirectoryException;
use Bead\Exceptions\Session\InvalidSessionFileException;
use Bead\Exceptions\Session\SessionDestroyedException;
use Bead\Exceptions\Session\SessionFileSaveException;
use Bead\Exceptions\Session\SessionNotFoundException;
use Bead\Session\Handlers\File;
use Bead\Session\Session;
use BeadTests\Framework\TestCase;
use Equit\XRay\StaticXRay;
use Equit\XRay\XRay;
use Mockery;
use Mockery\MockInterface;
use SplFileInfo;
use StdClass;

/** @covers \Bead\Session\Handlers\File */
class FileTest extends TestCase
{
    private const TestId = "bead-framework--bead-framework--bead-framework--bead-framework--";

    private const TestSession = [
        "created_at" => 1770928947,     // 2026-02-12T20:42:27.000Z
        "last_used_at" => 1770928965,   // 2026-02-12T20:42:45.000Z
        "id_created_at" => 1770928947,  // 2026-02-12T20:42:27.000Z
        "id_expired_at" => null,
        "replacement_id" => null,
        "data" => [
            "string" => "value",
            "int" => 42,
            "float" => 3.14,
            "true" => true,
            "false" => false,
            "null" => null,
            "array" => [1, 2, 3],
        ],
    ];

    /** @var Application&MockInterface */
    private Application $m_app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->m_app = Mockery::mock(Application::class);

        $this->m_app->expects("config")
            ->zeroOrMoreTimes()
            ->with("session.handlers.file.directory", "data/session")
            ->andReturn("")
            ->byDefault();

        $this->m_app->expects("rootDir")
            ->zeroOrMoreTimes()
            ->withNoArgs()
            ->andReturn($this->tempDir())
            ->byDefault();

        $xray = new StaticXRay(Application::class);
        $xray->s_instance = $this->m_app;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** Provides valid session ID strings. */
    public static function providerValidIds(): iterable
    {
        yield "every valid character" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_"];
    }

    /** Provides invalid session ID strings. */
    public static function providerInvalidIds(): iterable
    {
        yield "too short" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-"];
        yield "too long" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_a"];
        yield "empty" => [""];

        foreach (str_split(" !£%^&*()=+[]{};':@,.<>/?|`#~\$\"\\") as $ch) {
            yield "invalid character '{$ch}'" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-{$ch}"];
        }
    }

    /** Provides invalid session directories. */
    public static function providerInvalidSessionDirectories(): iterable
    {
        yield "parent" => [".."];
        yield "traverse up" => ["../../foo/bar/.."];
        yield "app root" => ["."];
    }

    /** Provides values that are not valid UNIX timestamps. */
    public static function providerInvalidTimestamps(): iterable
    {
        yield "string" => ["1770929456"];
        yield "float" => [1770929456.0];
        yield "true" => [true];
        yield "false" => [false];
        yield "array" => [[1770929456]];
        yield "null" => [null];
        yield "object" => [new StdClass()];
    }

    /** Provides values that are not valid UNIX timestamps or null. */
    public static function providerInvalidNullableTimestamps(): iterable
    {
        foreach (self::providerInvalidTimestamps() as $name => $value) {
            if (null === $value[0]) {
                continue;
            }

            yield $name => $value;
        }
    }

    /** Provides values that are not valid UNIX timestamps. */
    public static function providerInvalidReplacementIds(): iterable
    {
        yield "short-string" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-"];
        yield "long-string" => ["abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_a"];
        yield "float" => [1770929456.0];
        yield "true" => [true];
        yield "false" => [false];
        yield "array" => [[1770929456]];
        yield "object" => [new StdClass()];
    }

    /** Provides values that are not valid session data arrays. */
    public static function providerInvalidSessionData(): iterable
    {
        yield "string" => ["bead-framework"];
        yield "float" => [1770929456.0];
        yield "true" => [true];
        yield "false" => [false];
        yield "null" => [null];
        yield "object" => [new StdClass()];
    }

    /** Ensure the constructor generates a new ID when one is not given. */
    public function testConstructor1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        self::assertSame(self::TestId, $handler->id());
    }

    /** Ensure the constructor sets the timestamps correctly when a new session is generated. */
    public function testConstructor2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);
        $handler = new File();
        self::assertSame(1770669789, $handler->createdAt());
        self::assertSame(1770669789, $handler->lastUsedAt());
        self::assertSame(1770669789, $handler->idGeneratedAt());
    }

    /** Ensure the constructor throws the expected exception if the session file does not exist. */
    public function testConstructor3(): void
    {
        $id = str_repeat("--bead--", 8);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        $this->expectException(SessionNotFoundException::class);
        $this->expectExceptionMessage("The session file for {$id} does not exist or is not a file");
        $handler = new File($id);
    }

    /** Ensure the constructor throws the expected exception if the session file is not a file. */
    public function testConstructor4(): void
    {
        $id = str_repeat("--bead--", 8);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        mkdir(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        $this->expectException(SessionNotFoundException::class);
        $this->expectExceptionMessage("The session file for {$id} does not exist or is not a file");
        $handler = new File($id);
    }

    /** Ensure the constructor throws the expected exception if the session file is a symlink. */
    public function testConstructor5(): void
    {
        $id = str_repeat("--bead--", 8);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        touch(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
        symlink(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId, self::tempDir() . DIRECTORY_SEPARATOR . $id);
        $this->expectException(SessionNotFoundException::class);
        $this->expectExceptionMessage("The session file for {$id} is a link - links are not supported for security");
        $handler = new File($id);
    }

    /** Ensure the constructor throws the expected exception if the session file is unreadable. */
    public function testConstructor6(): void
    {
        $id = str_repeat("--bead--", 8);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        touch(self::tempDir() . DIRECTORY_SEPARATOR . $id);
        $this->mockMethod(SplFileInfo::class, "isReadable", false);
        $this->expectException(SessionNotFoundException::class);
        $this->expectExceptionMessage("The session file for {$id} is not readable");
        $handler = new File($id);
    }

    /** Ensure the constructor reloads the session file when given a valid ID. */
    public function testConstructor7(): void
    {
        $id = str_repeat("--bead--", 8);

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . $id,
            serialize([
                // 2026-02-09T20:43:09.000Z
                "created_at" => 1770669789,
                "last_used_at" => 1770669789,
                "id_created_at" => 1770669789,
                "id_expired_at" => null,
                "replacement_id" => null,
                "data" => [
                    "bead" => "framework",
                ]
            ]),
        );

        $handler = new File($id);
        self::assertSame(1770669789, $handler->createdAt());
        self::assertSame(1770669789, $handler->lastUsedAt());
        self::assertSame(1770669789, $handler->idGeneratedAt());
        self::assertSame(null, $handler->idExpiredAt());
        self::assertSame(null, $handler->replacementId());
        self::assertSame(["bead" => "framework"], $handler->all());
    }

    /** Ensure the session directory is read from the application config. */
    public function testSessionDirectory1(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.handlers.file.directory", "data" . DIRECTORY_SEPARATOR . "session")
            ->andReturn("custom/session/directory");

        $this->m_app->expects("rootDir")
            ->once()
            ->withNoArgs()
            ->andReturn("/bead/app");

        self::assertSame("/bead/app/custom/session/directory", (new StaticXRay(File::class))->sessionDirectory());
    }

    /**
     * Ensure an InvalidSessionDirectoryException is thrown if the configured session directory is not valid.
     * @dataProvider providerInvalidSessionDirectories
     */
    public function testSessionDirectory2(string $invalidDirectory): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.handlers.file.directory", "data" . DIRECTORY_SEPARATOR . "session")
            ->andReturn($invalidDirectory);

        $this->expectException(InvalidSessionDirectoryException::class);
        (new StaticXRay(File::class))->sessionDirectory();
    }

    /** Ensure a random ID is generated. */
    public function testCreateId1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
        self::assertSame(self::TestId, (new StaticXRay(File::class))->createId());
    }

    /** Ensure an ID that already has a file is rejected. */
    public function testCreateId2(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        touch(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . $testIds[1]);
        self::assertSame($testIds[1], (new StaticXRay(File::class))->createId());
    }

    /** Ensure the file for the generated ID is created. */
    public function testCreateId3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
        (new StaticXRay(File::class))->createId();
        self::assertFileExists(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
    }

    /**
     * Ensure valid IDs pass the validity check.
     * @dataProvider providerValidIds
     */
    public function testIsValidId1(string $id): void
    {
        self::assertTrue((new StaticXRay(File::class))->isValidId($id));
    }

    /**
     * Ensure invalid IDs don't pass the validity check.
     * @dataProvider providerInvalidIds
     */
    public function testIsValidId2(string $id): void
    {
        self::assertFalse((new StaticXRay(File::class))->isValidId($id));
    }

    /** Ensure throwIfDestroyed doesn't throw if the session hasn't been destroyed. */
    public function testThrowIfDestroyed1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new XRay(new File());
        self::assertFalse($handler->m_destroyed);

        // the test is a pass if this does not throw
        $handler->throwIfDestroyed();
    }

    /** Ensure throwIfDestroyed throws if the session has been destroyed. */
    public function testThrowIfDestroyed2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new XRay(new File());
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->throwIfDestroyed();
    }

    /** Ensure the ID can be retrieved. */
    public function testId1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        self::assertSame(self::TestId, $handler->id());
    }

    /** Ensure id() throws if the session has been destroyed. */
    public function testId2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->id();
    }

    /** Ensure the created timestamp can be retrieved. */
    public function testCreatedAt1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);
        $handler = new File();
        self::assertSame(1770669789, $handler->createdAt());
    }

    /** Ensure the timestamp at which the ID was generated can be retrieved. */
    public function testIdGeneratedAt1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);
        $handler = new File();
        self::assertSame(1770669789, $handler->idGeneratedAt());
    }

    /** Ensure the timestamp at which the session ID expired is null before the session ID has expired. */
    public function testIdExpiredAt1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);
        $handler = new File();
        self::assertNull($handler->idExpiredAt());
    }

    /** Ensure the timestamp at which the session ID expired can be retrieved for expired sessions. */
    public function testIdExpiredAt2(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $testTimestamps = [
            1770669789, // created 2026-02-09T20:43:09.000Z
            1770758396, // id expired at 2026-02-10T21:19:56.000Z
            1770758396, // all susbequent calls to time()
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $this->mockFunction(
            "time",
            static function () use (&$testTimestamps): int {
                if (1 === count($testTimestamps)) {
                    return $testTimestamps[0];
                }

                return array_shift($testTimestamps);
            },
        );

        (new File())->regenerateId();
        $handler = new File(self::TestId);
        self::assertSame(1770758396, $handler->idExpiredAt());
    }

    /** Ensure the last used timestamp can be retrieved. */
    public function testLastUsedAt1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);
        $handler = new File();
        self::assertSame(1770669789, $handler->lastUsedAt());
    }

    /** Ensure touch() sets the last used timestamp to the current time. */
    public function testTouch1(): void
    {
        $testTimestamps = [
            1770669789, // created 2026-02-09T20:43:09.000Z
            1770669789, // commit at 2026-02-09T20:43:09.000Z
            1770758396, // last used at 2026-02-10T21:19:56.000Z
            1770759881, // commit on destruction at 2026-02-10T21:44:41.000Z
        ];

        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        $this->mockFunction(
            "time",
            static function () use (&$testTimestamps): int {
                return array_shift($testTimestamps);
            },
        );

        $handler = new File();
        $handler->touch();
        self::assertSame(1770758396, $handler->lastUsedAt());
    }

    /** Ensure touch() sets the last used timestamp to the provided time. */
    public function testTouch2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        // created 2026-02-09T20:43:09.000Z
        $this->mockFunction("time", 1770669789);

        $handler = new File();
        $handler->touch(1770758396);
        self::assertSame(1770758396, $handler->lastUsedAt());
    }

    /** Ensure a value can be retrieved from the handler. */
    public function testGet1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "framework");
        self::assertSame("framework", $handler->get("bead"));
    }

    /** Ensure a null is returned when retrieving a non-existent key from the handler. */
    public function testGet2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        self::assertNull($handler->get("bead"));
    }

    /** Ensure get() throws when the session has been destroyed. */
    public function testGet3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->get("bead");
    }

    /** Ensure all() returns all the handler's data. */
    public function testAll1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "framework");
        $handler->set("library", "xray");

        self::assertSame([
            "bead" => "framework",
            "library" => "xray",
        ], $handler->all());
    }

    /** Ensure all() throws when the session has been destroyed. */
    public function testAll2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->all();
    }

    /** Ensure a new session variable can be set. */
    public function testSet1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "framework");
        self::assertSame("framework", $handler->get("bead"));
    }

    /** Ensure an existing session variable can be overwritten. */
    public function testSet2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "library");
        $handler->set("bead", "framework");
        self::assertSame(["bead" => "framework"], $handler->all());
    }

    /** Ensure set() throws when the session has been destroyed. */
    public function testSet3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->set("bead", "framework");
    }

    /** Ensure a session variable can be removed. */
    public function testRemove1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "framework");
        $handler->remove("bead");
        self::assertSame(null, $handler->get("bead"));
    }

    /** Ensure removing a non-existent session variable is a no-op. */
    public function testRemove2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->remove("bead");
        self::assertSame(null, $handler->get("bead"));
    }

    /** Ensure remove() throws when the session has been destroyed. */
    public function testRemove3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->remove("bead");
    }

    /** Ensure the session can be cleard. */
    public function testClear1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->set("bead", "framework");
        $handler->set("xray", "library");
        $handler->clear();
        self::assertSame([], $handler->all());
    }

    /** Ensure clear() throws when the session has been destroyed. */
    public function testClear3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->clear();
    }

    /** Ensure regenerating the ID changes the ID. */
    public function testRegenerateId1(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $handler = new File();
        $handler->regenerateId();
        self::assertSame(str_repeat("--bead--", 8), $handler->id());
    }

    /** Ensure regenerating the ID sets the replacement ID on the original session. */
    public function testRegenerateId2(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        (new File())->regenerateId();
        $handler = new File(self::TestId);
        self::assertSame(str_repeat("--bead--", 8), $handler->replacementId());
    }

    /** Ensure regenerating the ID sets the expired at timestamp. */
    public function testRegenerateId3(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $testTimestamps = [
            1770669789, // constructor: created 2026-02-09T20:43:09.000Z
            1770758396, // commit(): last used at 2026-02-10T21:19:56.000Z

            // this is the expected timestamp from idExpiredAt()
            1770759881, // regenerateId(): old ID expired at 2026-02-10T21:44:41.000Z

            1770839614, // commit(): old ID last used at 2026-02-10T21:44:41.000Z
            1770839614, // regenerateId(): new ID created at 2026-02-11T19:53:34.000Z
            1770839614, // commit(): new ID last used at 2026-02-11T19:53:34.000Z
            1770839614, // commit() [destructor]: new session last used at 2026-02-11T19:53:34.000Z
            1770839614, // commit() [destructor]: old session last used at 2026-02-11T19:53:34.000Z
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $this->mockFunction(
            "time",
            static function () use (&$testTimestamps): int {
                return array_shift($testTimestamps);
            },
        );

        (new File())->regenerateId();
        $handler = new File(self::TestId);
        self::assertSame(1770759881, $handler->idExpiredAt());
    }

    /** Ensure the ID expiry timestamp for the session is null after regenerating the ID. */
    public function testRegenerateId4(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $handler = new File();
        $handler->regenerateId();
        self::assertNull($handler->idExpiredAt());
    }

    /** Ensure the ID creation timestamp for the session is set correctly after regenerating the ID. */
    public function testRegenerateId5(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $testTimestamps = [
            1770669789, // constructor: created 2026-02-09T20:43:09.000Z
            1770758396, // commit(): last used at 2026-02-10T21:19:56.000Z
            1770759881, // regenerateId(): old ID expired at 2026-02-10T21:44:41.000Z
            1770839614, // commit(): old ID last used at 2026-02-10T21:44:41.000Z

            // this is the expected timestamp from idExpiredAt()
            1770841210, // regenerateId(): new ID created at 2026-02-11T20:20:10.000Z

            1770841231, // commit(): new ID last used at 2026-02-11T20:20:31.000Z
            1770841231, // commit() [destructor]: new session last used at 2026-02-11T20:20:31.000Z
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $this->mockFunction(
            "time",
            static function () use (&$testTimestamps): int {
                return array_shift($testTimestamps);
            },
        );

        $handler = new File();
        $handler->regenerateId();
        self::assertSame(1770841210, $handler->idGeneratedAt());
    }

    /** Ensure regenerateId() returns the new session ID. */
    public function testRegenerateId6(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $handler = new File();
        self::assertSame(str_repeat("--bead--", 8), $handler->regenerateId());
    }

    /** Ensure regenerateId() throws if the session has been destroyed. */
    public function testRegenerateId7(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->regenerateId();
    }

    /** Ensure idHasExpired() correctly reports when the session's ID has not expired. */
    public function testIdHasExpired1(): void
    {
        self::assertFalse((new File())->idHasExpired());
    }

    /** Ensure idHasExpired() correctly reports when the session's ID has expired. */
    public function testIdHasExpired2(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        (new File())->regenerateId();
        self::assertTrue((new File(self::TestId))->idHasExpired());
    }

    /** Ensure idHasExpired() throws if the session has been destroyed. */
    public function testIdHasExpired3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->idHasExpired();
    }

    /** Ensure the replacement ID is null before the session ID has been regenerated. */
    public function testReplacementId1(): void
    {
        self::assertNull((new File())->replacementId());
    }

    /** Ensure the replacement ID for the old session is correct after the session ID has been regenerated. */
    public function testReplacementId2(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        (new File())->regenerateId();
        self::assertSame(str_repeat("--bead--", 8), (new File(self::TestId))->replacementId());
    }

    /** Ensure the replacement ID for the new session is null after the session ID has been regenerated. */
    public function testReplacementId3(): void
    {
        $testIds = [
            self::TestId,
            str_repeat("--bead--", 8),
        ];

        $this->mockFunction(
            "Bead\\Helpers\\Str\\random",
            static function (int $length) use (&$testIds): string {
                return array_shift($testIds);
            },
        );

        $handler = new File();
        $handler->regenerateId();
        self::assertNull($handler->replacementId());
    }

    /** Ensure replacementId() throws if the session has been destroyed. */
    public function testReplacementId4(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->replacementId();
    }

    /** Ensure commit() sets the last used timestamp. */
    public function testCommit1(): void
    {
        $testTimestamps = [
            1770669789, // constructor: created 2026-02-09T20:43:09.000Z
            1770758396, // commit() [constructor]: last used at 2026-02-10T21:19:56.000Z

            // this is the expected last used timestamp after the call to commit()
            1770839614, // commit(): last used at 2026-02-10T21:44:41.000Z

            1770841231, // commit() [destructor]: new session last used at 2026-02-11T20:20:31.000Z
        ];

        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);

        $this->mockFunction(
            "time",
            static function () use (&$testTimestamps): int {
                return array_shift($testTimestamps);
            },
        );

        $handler = new File();
        $handler->commit();
        self::assertSame(1770839614, $handler->lastUsedAt());
    }

    /** Ensure commit() creates the expected session file. */
    public function testCommit2(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $this->mockFunction("touch", true);
        $this->mockFunction("file_put_contents", 0);
        $handler = new File();
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
        $this->removeFunctionMock("file_put_contents");
        $handler->commit();
        self::assertFileExists(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
    }

    /** Ensure commit() throws if the session file cannot be written. */
    public function testCommit3(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $this->mockFunction("file_put_contents", false);
        $this->expectException(SessionFileSaveException::class);
        $this->expectExceptionMessageMatches("/Failed to commit the session to the file \".*\"/");
        $handler->commit();
    }

    /** Ensure commit() throws if the session has been destroyed. */
    public function testCommit4(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->commit();
    }

    /** Ensure reload() correctly reads a session file. */
    public function testReload1(): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $handler->reload();
        self::assertSame(self::TestSession["created_at"], $handler->createdAt());
        self::assertSame(self::TestSession["last_used_at"], $handler->lastUsedAt());
        self::assertSame(self::TestSession["id_created_at"], $handler->idGeneratedAt());
        self::assertNull($handler->idExpiredAt());
        self::assertNull($handler->replacementId());
        self::assertSame(self::TestSession["data"]["string"], $handler->get("string"));
        self::assertSame(self::TestSession["data"]["int"], $handler->get("int"));
        self::assertSame(self::TestSession["data"]["float"], $handler->get("float"));
        self::assertSame(self::TestSession["data"]["true"], $handler->get("true"));
        self::assertSame(self::TestSession["data"]["false"], $handler->get("false"));
        self::assertSame(self::TestSession["data"]["array"], $handler->get("array"));
        self::assertNull($handler->get("null"));
    }

    /**
     * Ensure reload() throws when the creation timestamp is invalid.
     * @dataProvider providerInvalidTimestamps
     */
    public function testReload2(mixed $invalidTimestamp): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["created_at"] = $invalidTimestamp;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid created-at timestamp/");
        $handler->reload();
    }

    /**
     * Ensure reload() throws when the last used timestamp is invalid.
     * @dataProvider providerInvalidTimestamps
     */
    public function testReload3(mixed $invalidTimestamp): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["last_used_at"] = $invalidTimestamp;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid last-used-at timestamp/");
        $handler->reload();
    }

    /**
     * Ensure reload() throws when the ID generation timestamp is invalid.
     * @dataProvider providerInvalidTimestamps
     */
    public function testReload4(mixed $invalidTimestamp): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["id_created_at"] = $invalidTimestamp;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid id-created-at timestamp/");
        $handler->reload();
    }

    /**
     * Ensure reload() throws when the ID expiry timestamp is invalid.
     * @dataProvider providerInvalidNullableTimestamps
     */
    public function testReload5(mixed $invalidTimestamp): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["id_expired_at"] = $invalidTimestamp;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid expired-at timestamp/");
        $handler->reload();
    }

    /**
     * Ensure reload() throws when the replacement ID is invalid.
     * @dataProvider providerInvalidReplacementIds
     */
    public function testReload6(mixed $invalidReplacementId): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["replacement_id"] = $invalidReplacementId;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid replacement ID/");
        $handler->reload();
    }

    /**
     * Ensure reload() throws when the session data array is invalid.
     * @dataProvider providerInvalidSessionData
     */
    public function testReload7(mixed $invalidSessionData): void
    {
        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new File(self::TestId);
        $invalidSession = self::TestSession;
        $invalidSession["data"] = $invalidSessionData;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($invalidSession),
        );

        $this->expectException(InvalidSessionFileException::class);
        $this->expectExceptionMessageMatches("/The session file \".*" . self::TestId . "\" contains an invalid data array/");
        $handler->reload();
    }

    /** Ensure reload() throws if the session has been destroyed. */
    public function testReload8(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        $handler->destroy();
        $this->expectException(SessionDestroyedException::class);
        $this->expectExceptionMessage("The session " . self::TestId . " has been destroyed and cannot be used");
        $handler->reload();
    }

    /** Ensure destroy() removes the session file. */
    public function testDestroy1(): void
    {
        $this->mockFunction("Bead\\Helpers\\Str\\random", self::TestId);
        $handler = new File();
        self::assertFileExists(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
        $handler->destroy();
        self::assertFileDoesNotExist(self::tempDir() . DIRECTORY_SEPARATOR . self::TestId);
    }

    /**
     * Ensure canBePurged() returns false when the session has been used just within the timeout threshold and the ID
     * has not expired.
     */
    public function testCanBePurged1(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        $this->mockFunction("time", self::TestSession["last_used_at"] + Session::DefaultSessionIdleTimeoutPeriod);

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new XRay(new File(self::TestId));
        self::assertFalse($handler->canBePurged());
    }

    /**
     * Ensure canBePurged() returns true when the session was last used just outside the timeout threshold and the ID
     * has not expired.
     */
    public function testCanBePurged2(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        $this->mockFunction("time", self::TestSession["last_used_at"] + Session::DefaultSessionIdleTimeoutPeriod + 1);

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        $handler = new XRay(new File(self::TestId));
        self::assertTrue($handler->canBePurged());
    }

    /**
     * Ensure canBePurged() returns false when the session was last used just within the timeout threshold and the ID
     * expired just within the grace period.
     */
    public function testCanBePurged3(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        $this->m_app->expects("config")
            ->once()
            ->with("session.expired.grace-period", Session::DefaultExpiryGracePeriod)
            ->andReturn(Session::DefaultExpiryGracePeriod);

        $now = self::TestSession["last_used_at"] + Session::DefaultSessionIdleTimeoutPeriod;
        $this->mockFunction("time", $now);
        $session = self::TestSession;
        $session["id_expired_at"] = $now - Session::DefaultExpiryGracePeriod;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($session),
        );

        $handler = new XRay(new File(self::TestId));
        self::assertFalse($handler->canBePurged());
    }

    /**
     * Ensure canBePurged() returns true when the session was last used just within the timeout threshold but the ID
     * expired outside the grace period.
     */
    public function testCanBePurged4(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        $this->m_app->expects("config")
            ->once()
            ->with("session.expired.grace-period", Session::DefaultExpiryGracePeriod)
            ->andReturn(Session::DefaultExpiryGracePeriod);

        $now = self::TestSession["last_used_at"] + Session::DefaultSessionIdleTimeoutPeriod;
        $this->mockFunction("time", $now);
        $session = self::TestSession;
        $session["id_expired_at"] = $now - Session::DefaultExpiryGracePeriod - 1;

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize($session),
        );

        $handler = new XRay(new File(self::TestId));
        self::assertTrue($handler->canBePurged());
    }

    /** Ensure prune() ignores .hiddent files. */
    public function testPrune1(): void
    {
        touch(self::tempDir() . "/.gitignore");
        File::prune();
        self::assertFileExists(self::tempDir() . "/.gitignore");
    }

    /** Ensure prune logs directories it discovers in the session directory. */
    public function testPrune2(): void
    {
        mkdir(self::tempDir() . "/directory");

        $log = Mockery::mock(LoggerContract::class);

        $log->expects("warning")
            ->once()
            ->with("Session directory entry " . self::tempDir() . "/directory is not a file or is not readable when purging session directory");

        $this->m_app->expects("get")
            ->once()
            ->with(LoggerContract::class)
            ->andReturn($log);

        File::prune();
        self::markTestAsExternallyVerified();
    }

    /** Ensure prune logs unreadable files it discovers in the session directory. */
    public function testPrune3(): void
    {
        file_put_contents(self::tempDir() . "/" . self::TestId, serialize(self::TestSession));
        chmod(self::tempDir() . "/" . self::TestId, 0000);

        $log = Mockery::mock(LoggerContract::class);

        $log->expects("warning")
            ->once()
            ->with("Session directory entry " . self::tempDir() . "/" . self::TestId . " is not a file or is not readable when purging session directory");

        $this->m_app->expects("get")
            ->once()
            ->with(LoggerContract::class)
            ->andReturn($log);

        File::prune();
        self::markTestAsExternallyVerified();
    }

    /** Ensure prune() leaves sessions that are not due to be purged. */
    public function testPrune4(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        // 2026-02-12T20:42:45.000Z - same as session last used
        $this->mockFunction("time", 1770928965);

        file_put_contents(self::tempDir() . "/" . self::TestId, serialize(self::TestSession));
        File::prune();
        self::assertFileExists(self::tempDir() . "/" . self::TestId);
    }

    /** Ensure prune() cleans up sessions that are due to be purged. */
    public function testPrune5(): void
    {
        $this->m_app->expects("config")
            ->once()
            ->with("session.idle-timeout-period", Session::DefaultSessionIdleTimeoutPeriod)
            ->andReturn(Session::DefaultSessionIdleTimeoutPeriod);

        $this->mockFunction("time", self::TestSession["last_used_at"] + Session::DefaultSessionIdleTimeoutPeriod + 1);

        file_put_contents(
            self::tempDir() . DIRECTORY_SEPARATOR . self::TestId,
            serialize(self::TestSession),
        );

        file_put_contents(self::tempDir() . "/" . self::TestId, serialize(self::TestSession));
        File::prune();
        self::assertFileDoesNotExist(self::tempDir() . "/" . self::TestId);
    }

    /** Ensure prune() logs exceptions reading session files. */
    public function testPrune6(): void
    {
        $log = Mockery::mock(LoggerContract::class);

        $log->expects("error")
            ->once()
            ->with("Exception reading session file " . self::tempDir() . "/" . self::TestId . " when purging session directory: The session file \"/tmp/bead-framework/test//bead-framework--bead-framework--bead-framework--bead-framework--\" contains an invalid created-at timestamp");

        $this->m_app->expects("get")
            ->once()
            ->with(LoggerContract::class)
            ->andReturn($log);

        touch(self::tempDir() . "/" . self::TestId);
        File::prune();
        self::markTestAsExternallyVerified();
    }
}
