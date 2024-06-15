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
        $this->addOption("migrations-dir", "d", "Load migrations for a specified directory (relative to the application's root directory.", type: self::TypeString, optional: true, default: "Migrations");
        $this->addOption("namespace", "n", "Override the namespace expected for migration classes defined in the db config file.", type: self::TypeString, optional: true);
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

    private function migrationsNamespace(): string
    {
        return ($this->optionIsSet("namespace") ? $this->optionValue("namespace"): $this->config("db.migrations.namespace"));
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

    private function readExecutedMigrations(): array
    {
        $db = $this->database();
        $table = $this->config("db.migrations.table");

        $sqlMigrations = $db->createQuery()
            ->select(["class" => "m.class"])
            ->from($table, "m")
            ->sql();

        return array_map(static fn (array $row): string => $row["class"], $db->prepare($sqlMigrations)->fetchAll());
    }

    /** @param class-string $class */
    private function migrateUp(string $class): void
    {
        try {
            $migration = new $fqClassName();
        } catch (Throwable $err) {
            throw new RuntimeException($err::class . " thrown instantiating migration class {$fqClassName} from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->up($db);
        } catch (Throwable $err) {
            $db->rollBack();
            throw new RuntimeException($err::class . " thrown in {$fqClassName}::up() from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db->commit();
    }

    /** @param class-string $class */
    private function migrateDown(string $class): void
    {
        try {
            $migration = new $fqClassName();
        } catch (Throwable $err) {
            throw new RuntimeException($err::class . " thrown instantiating migration class {$fqClassName} from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->down($db);
        } catch (Throwable $err) {
            $db->rollBack();
            throw new RuntimeException($err::class . " thrown in {$fqClassName}::down() from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db->commit();
    }

    /**
     * @inheritDoc
     */
    protected function run(): int
    {
        $namespace = $this->migrationsNamespace();
        $executedMigrations = $this->readExecutedMigrations();

        foreach ($this->listMigrations() as $migrationClass => $migrationFileName) {
            $fqClassName = "{$namespace}\\{$migrationClass}";

            if (in_array($migrationClass, $executedMigrations)) {
                continue;
            }

            @include $migrationFileName;

            if (!class_exists($fqClassName)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\" in namespace \"{$namespace}\"");
            }

            if (!is_a($fqClassName, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            $this->migrateUp($fqClassName);
        }
    }
}