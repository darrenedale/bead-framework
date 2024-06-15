<?php

declare(strict_types=1);

use Bead\Contracts\Database\Migration as MigrationContract;
use Bead\Core\ConsoleApplication;
use Bead\Database\Column;
use Bead\Database\ColumnSize;
use Bead\Database\NullabilityConstraint;
use Bead\Database\Table;

class Migrations extends ConsoleApplication
{
    private const CommandMigrate = "migrate";

    private const CommandReset = "reset";

    private const CommandNext = "next";

    private const CommandPrevious = "previous";

    private const CommandCurrent = "current";

    private const CommandList = "list";

    private const CommandListExecuted = "list-executed";

    private const CommandListPending = "list-pending";

    private const Columns = [
        "class",
        "description",
        "executed_at",
        "execution_duration",
    ];

    public function __construct(array $args)
    {
        parent::__construct(__DIR__ . "/../../../../", $args);
    }

    protected function configure(): void
    {
        $this->setDescription("Run bead-framework migrations.");
        $this->addFlag("verbose", "v", "Output more information about what operations are being performed.", negatable: false);
        $this->addOption("migrations-dir", "d", "Load migrations for a specified directory (relative to the application's root directory.", type: self::TypeString, optional: true, default: "db/migrations");
        $this->addArgument("command", "What the command should do: migrate, reset, next, previous, current");
    }

    private function migrationsTable(): string
    {
        return $this->config("db.migrations.table", "bead_framework_migrations");
    }

    private function createMigrationsTable(): void
    {
        $table = (new Table($this->migrationsTable()))
            ->withColumn(
                (new Column("class", Column::Varchar))
                    ->withSize(new ColumnSize(200))
                    ->withConstraint(new NullabilityConstraint(false))
            )
            ->withColumn(
                (new Column("description", Column::Text))
                    ->withConstraint(new NullabilityConstraint(false))
            )
            ->withColumn(
                (new Column("executed_at", Column::UnsignedBigInteger))
                    ->withConstraint(new NullabilityConstraint(false))
            )
            ->withColumn(
                (new Column("execution_duration", Column::Integer))
                    ->withConstraint(new NullabilityConstraint(false))
            );

        try {
            $this->database()->createTable($table);
        } catch (Throwable $err) {
            throw new RuntimeException("Unable to create migrations table \"{$this->migrationsTable()}\": {$err->getMessage()}", previous: $err);
        }
    }

    private function checkMigrationsTable(): void
    {
        $tableName = $this->migrationsTable();
        $db = $this->database();

        if (!$db->hasTable($tableName)) {
            $this->createMigrationsTable();
            return;
        }

        try {
            $sql = $db->createQuery()
                ->select(self::Columns)
                ->from($tableName)
                ->limit(1)
                ->sql();

            $db->prepare($sql)->execute();
        } catch (Throwable $err) {
            throw new RuntimeException("Migrations table \"{$table}\" is not usable", previous: $err);
        }

        // TODO check column types
    }

    private function migrationsDirectory(): string
    {
        $dir = $this->optionValue("migrations-dir");

        if (".." === $dir || str_contains($dir, "../") || str_contains($dir, "/..")) {
            throw new InvalidArgumentException("Migrations directory must not contain any parent-directory traversal (\"{$dir}\" is not valid).");
        }

        return "{$this->rootDir()}/{$this->optionValue("migrations-dir")}";
    }

    private function listMigrations(): array
    {
        $migrations = [];

        /** @var SplFileInfo $entry */
        foreach (new FilesystemIterator($this->migrationsDirectory()) as $entry) {
            if ("php" !== $entry->getExtension()) {
                continue;
            }

            $migrations[$entry->getBasename(".php")] = $entry->getPathname();
        }

        uksort ($migrations, static fn (string $class1, string $class2) => $class1 <=> $class2);
        return $migrations;
    }

    private function executedMigrations(): array
    {
        $db = $this->database();
        $table = $this->config("db.migrations.table");

        $sqlMigrations = $db->createQuery()
            ->select(["class" => "m.class", "description" => "m.description", "executed_at" => "m.executed_at", "execution_duration" => "m.execution_duration",])
            ->from($table, "m")
            ->orderBy("m.class", "DESC")
            ->sql();

        $stmt = $db->prepare($sqlMigrations);
        $stmt->execute();
        return array_column($stmt->fetchAll(), null, "class");
    }

    /** @param class-string $class */
    private function migrateUp(string $class): void
    {
        try {
            /** @var MigrationContract $migration */
            $migration = new $class();
        } catch (Throwable $err) {
            throw new RuntimeException($err::class . " thrown instantiating migration class {$fqClassName}: {$err->getMessage()}", previous: $err);
        }

        $start = microtime(true);
        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->up($db);
        } catch (Throwable $err) {
            $db->rollBackTransaction();
            throw new RuntimeException($err::class . " thrown in {$class}::up(): {$err->getMessage()}", previous: $err);
        }

        if ($db->inTransaction()) {
            $db->commitTransaction();
        }

        $duration = microtime(true) - $start;

        $db->insert(
            $this->migrationsTable(),
            [
                [
                    "class" => $class,
                    "description" => $migration->description(),
                    "executed_at" => (int) $start,
                    "execution_duration" => (int) ($duration * 100000),
                ],
            ]
        );
    }

    /** @param class-string $class */
    private function migrateDown(string $class): void
    {
        try {
            $migration = new $class();
        } catch (Throwable $err) {
            throw new RuntimeException($err::class . " thrown instantiating migration class {$class}: {$err->getMessage()}", previous: $err);
        }

        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->down($db);
        } catch (Throwable $err) {
            $db->rollBack();
            throw new RuntimeException($err::class . " thrown in {$fqClassName}::down(): {$err->getMessage()}", previous: $err);
        }

        $db->commit();
    }

    protected function run(): int
    {
        $this->checkMigrationsTable();

        match ($this->argumentValue("command")) {
            self::CommandMigrate => $this->commandMigrate(),
            self::CommandReset => $this->commandReset(),
            self::CommandNext => $this->commandNext(),
            self::CommandPrevious => $this->commandPrevious(),
            self::Commandcurrent => $this->commandCurrent(),
            self::CommandList => $this->commandList(),
            self::CommandListExecuted => $this->commandListExecuted(),
            self::CommandListPending => $this->commandListPending(),
            default => throw new InvalidArgumentException("Command {$this->argumentValue("command")} not recognised."),
        };

        return 0;
    }

    private function commandMigrate(): void
    {
        $executedMigrations = $this->executedMigrations();

        if (0 === count($executedMigrations)) {
            $this->line("No previously executed migrations.");
        } else {
            $this->line(count($executedMigrations) . " previously executed migrations.");
            $currentMigration = array_key_first($executedMigrations);
            $this->line("Currently migrated to {$currentMigration}.");
        }

        foreach ($this->listMigrations() as $migrationClass => $migrationFileName) {
            if (array_key_exists($migrationClass, $executedMigrations)) {
                $this->line("Migration {$migrationClass} executed at {$executedMigrations[$migrationClass]["executed_at"]}.");
                continue;
            }

            @include $migrationFileName;

            if (!class_exists($migrationClass)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
            }

            if (!is_a($migrationClass, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            $this->line("Executing migration {$migrationClass}.");
            $this->migrateUp($migrationClass);
        }
    }

    private function commandReset(): void
    {
        $this->errorLine("Not yet implemented.");
    }

    private function commandNext(): void
    {
        $executedMigrations = $this->executedMigrations();

        foreach ($this->listMigrations() as $migrationClass => $migrationFileName) {
            if (array_key_exists($migrationClass, $executedMigrations)) {
                continue;
            }

            @include $migrationFileName;

            if (!class_exists($migrationClass)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
            }

            if (!is_a($migrationClass, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            $this->line("Executing migration {$migrationClass}.");
            $this->migrateUp($migrationClass);
            break;
        }
    }

    private function commandPrevious(): void
    {
        $this->errorLine("Not yet implemented.");
    }

    private function commandcurrent(): void
    {
        $migrations = $this->executedMigrations();

        if (0 === count($migrations)) {
            $this->line("No migrations have been executed.");
        } else {
            $currentMigration = array_key_first($executedMigrations);
            $this->line("Currently migrated to {$currentMigration}.");
        }
    }

    private function commandList(): void
    {
        $executedMigrations = $this->executedMigrations();

        foreach ($this->listMigrations() as $class => $path) {
            $message = "{$class} in {$path}";

            if (array_key_exists($class, $executedMigrations)) {
                $executedDate = DateTimeImmutable::createFromFormat("U", "{$executedMigrations[$class]["executed_at"]}", new DateTimeZone("UTC"));
                $message .= " [{$executedDate->format("Y-m-d H:i:s")} UTC, {$executedMigrations[$class]["execution_duration"]}ms]";
            }

            $this->line($message);
        }
    }

    private function commandListExecuted(): void
    {
        foreach ($this->executedMigrations() as $class => $details) {
            $executedDate = DateTimeImmutable::createFromFormat("U", "{$executedMigrations[$class]["executed_at"]}", new DateTimeZone("UTC"));
            $this->line("{$class} in {$path} [{$executedDate->format("Y-m-d H:i:s")} UTC, {$executedMigrations[$class]["execution_duration"]}ms]");
        }
    }

    private function commandListPending(): void
    {
        $executedMigrations = $this->executedMigrations();

        foreach ($this->listMigrations() as $class => $path) {
            if (array_key_exists($class, $executedMigrations)) {
                continue;
            }

            $this->line("{$class} in {$path}");
        }
    }
}
