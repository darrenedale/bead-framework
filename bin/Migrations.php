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
    private const ActionMigrate = "migrate";

    private const ActionReset = "reset";

    private const ActionNext = "next";

    private const ActionPrevious = "previous";

    private const ActionCurrent = "current";

    private const ActionList = "list";

    private const ActionListExecuted = "list-executed";

    private const ActionListPending = "list-pending";

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
                    ->withComment("Unix timestamp of the time at which the migration was executed.")
            )
            ->withColumn(
                (new Column("execution_duration", Column::Integer))
                    ->withConstraint(new NullabilityConstraint(false))
                    ->withComment("The number of ms the migration took to execute.")
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

    private function availableMigrations(): array
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

        $startTime = time();
        $start = hrtime(true);
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

        $nanoSeconds = hrtime(true) - $start;

        $db->insert(
            $this->migrationsTable(),
            [
                [
                    "class" => $class,
                    "description" => $migration->description(),
                    "executed_at" => $startTime,
                    "execution_duration" => (int) ($duration / 1000000),
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

        if ($db->inTransaction()) {
            $db->commitTransaction();
        }

        $db->delete($this->migrationsTable(), ["class" => $class,]);
    }

    protected function run(): int
    {
        $this->checkMigrationsTable();

        match ($this->argumentValue("action")) {
            self::ActionMigrate => $this->migrateAction(),
            self::ActionReset => $this->resetAction(),
            self::ActionNext => $this->nextAction(),
            self::ActionPrevious => $this->previousAction(),
            self::ActionCurrent => $this->currentAction(),
            self::ActionList => $this->listAction(),
            self::ActionListExecuted => $this->listExecutedAction(),
            self::ActionListPending => $this->listPendingAction(),
            default => throw new InvalidArgumentException("Action {$this->argumentValue("command")} not recognised."),
        };

        return 0;
    }

    private function migrateAction(): void
    {
        $availableMigrations = $this->availableMigrations();

        if (0 === $availableMigrations) {
            $this->line("There are no available migrations.");
            return;
        }

        $executedMigrations = $this->executedMigrations();
        $verbose = $this->flagValue("verbose");
        $count = 0;

        if ($verbose) {
            if (0 === count($executedMigrations)) {
                $this->line("No previously executed migrations.");
            } else {
                $this->line(count($executedMigrations) . " previously executed migrations.");
                $currentMigration = array_key_first($executedMigrations);
                $this->line("Currently migrated to {$currentMigration}.");
            }
        }

        foreach ($availableMigrations as $migrationClass => $migrationFileName) {
            if (array_key_exists($migrationClass, $executedMigrations)) {
                if ($verbose) {
                    $this->line("Migration {$migrationClass} executed at {$executedMigrations[$migrationClass]["executed_at"]}.");
                }

                continue;
            }

            @include $migrationFileName;

            if (!class_exists($migrationClass)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
            }

            if (!is_a($migrationClass, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            if ($verbose) {
                $this->line("Executing migration {$migrationClass}.");
            }

            $this->migrateUp($migrationClass);
            ++$count;
        }

        if (0 === $count) {
            $this->line("No migrations performed - already migrated to " . array_key_last($availableMigrations) . ".");
        } else {
            $this->line("Now migrated to {{$migrationClass}}.");
        }
    }

    private function resetAction(): void
    {
        $executedMigrations = $this->executedMigrations();

        if (0 === count($executedMigrations)) {
            $this->line("No migrations have been executed yet.");
            return;
        }

        $verbose = $this->flagValue("verbose");
        $migrationsDirectory = $this->migrationsDirectory();

        foreach (array_keys($executedMigrations) as $migrationClass) {
            $migrationFileName = "{$migrationsDirectory}/{$migrationClass}.php";

            @include $migrationFileName;

            if (!class_exists($migrationClass)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
            }

            if (!is_a($migrationClass, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            if ($verbose) {
                $this->line("Rolling back migration {$migrationClass}.");
            }

            $this->migrateDown($migrationClass);
        }

        $this->line("Rolled back " . count($executedMigrations) . " migrations.");
    }

    private function nextAction(): void
    {
        $verbose = $this->flagValue("verbose");
        $availableMigrations = $this->availableMigrations();

        if (0 === count($availableMigrations)) {
            $this->line("There are no available migrations.");
            return;
        }

        $executedMigrations = $this->executedMigrations();
        $count = 0;

        foreach ($availableMigrations as $migrationClass => $migrationFileName) {
            if (array_key_exists($migrationClass, $executedMigrations)) {
                if ($verbose) {
                    $this->line("Migration {$migrationClass} already executed, skipping.");
                }

                continue;
            }

            @include $migrationFileName;

            if (!class_exists($migrationClass)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
            }

            if (!is_a($migrationClass, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            if ($verbose) {
                $this->line("Executing migration {$migrationClass}.");
            }

            $this->migrateUp($migrationClass);
            ++$count;
            break;
        }

        if (0 === $count) {
            $this->line("No migrations performed - already migrated to " . array_key_last($availableMigrations) . ".");
        } else {
            $this->line("Now migrated to {{$migrationClass}}.");
        }
    }

    private function previousAction(): void
    {
        $executedMigrations = $this->executedMigrations();

        if (0 === count($executedMigrations)) {
            $this->line("No migrations have been executed yet.");
            return;
        }

        $verbose = $this->flagValue("verbose");
        $migrationsDirectory = $this->migrationsDirectory();
        $migrationClass = array_key_last($executedMigrations);
        $migrationFileName = "{$migrationsDirectory}/{$migrationClass}.php";

        @include $migrationFileName;

        if (!class_exists($migrationClass)) {
            throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\"");
        }

        if (!is_a($migrationClass, MigrationContract::class, true)) {
            throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
        }

        if ($verbose) {
            $this->line("Rolling back migration {$migrationClass}.");
        }

        $this->migrateDown($migrationClass);
        array_pop($executedMigrations);
        $currentMigration = array_key_last($executedMigrations);
        $this->line("Now migrated to {$currentMigration}.");
    }

    private function currentAction(): void
    {
        $migrations = $this->executedMigrations();

        if (0 === count($migrations)) {
            $this->line("No migrations have been executed.");
        } else {
            $currentMigration = array_key_first($migrations);
            $this->line("Currently migrated to {$currentMigration}.");
        }
    }

    private function listAction(): void
    {
        $executedMigrations = $this->executedMigrations();

        foreach ($this->availableMigrations() as $class => $path) {
            $message = "{$class} in {$path}";

            if (array_key_exists($class, $executedMigrations)) {
                $executedDate = DateTimeImmutable::createFromFormat("U", "{$executedMigrations[$class]["executed_at"]}", new DateTimeZone("UTC"));
                $message .= " [{$executedDate->format("Y-m-d H:i:s")} UTC, {$executedMigrations[$class]["execution_duration"]}ms]";
            }

            $this->line($message);
        }
    }

    private function listExecutedAction(): void
    {
        foreach ($this->executedMigrations() as $class => $details) {
            $executedDate = DateTimeImmutable::createFromFormat("U", "{$details["executed_at"]}", new DateTimeZone("UTC"));
            $this->line("{$class} [{$executedDate->format("Y-m-d H:i:s")} UTC, {$details["execution_duration"]}ms]");
        }
    }

    private function listPendingAction(): void
    {
        $executedMigrations = $this->executedMigrations();

        foreach ($this->availableMigrations() as $class => $path) {
            if (array_key_exists($class, $executedMigrations)) {
                continue;
            }

            $this->line("{$class} in {$path}");
        }
    }
}
