<?php
declare(strict_types=1);

use Bead\Contracts\Database\Migration as MigrationContract;

class Migrations extends \Bead\Core\ConsoleApplication
{
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
        return ($this->hasOption("namespace") ? $this->optionValue("namespace"): $this->config("db.migrations.namespace"));
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
            throw new RuntimeException("{$err::class} thrown instantiating migration class {$fqClassName} from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->up($db);
        } catch (Throwable $err) {
            $db->rollBack();
            throw new RuntimeException("{$err::class} thrown in {$fqClassName}::up() from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db->commit();
    }

    /** @param class-string $class */
    private function migrateDown(string $class): void
    {
        try {
            $migration = new $fqClassName();
        } catch (Throwable $err) {
            throw new RuntimeException("{$err::class} thrown instantiating migration class {$fqClassName} from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
        }

        $db = $this->database();
        $db->beginTransaction();

        try {
            $migration->down($db);
        } catch (Throwable $err) {
            $db->rollBack();
            throw new RuntimeException("{$err::class} thrown in {$fqClassName}::down() from file \"{$migrationFileName}\": {$err->getMessage()}", previous: $err);
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
            @include $migrationFileName;

            if (!class_exists($fqClassName)) {
                throw new RuntimeException("Migration file \"{$migrationFileName}\" does not define the expected class \"{$migrationClass}\" in namespace \"{$namespace}\"");
            }

            if (!is_a($fqClassName, MigrationContract::class, true)) {
                throw new RuntimeException("Class {$migrationClass} in migration file \"{$migrationFileName}\" does not implement " . MigrationContract::class);
            }

            if (in_array($fqClassName, $executedMigrations)) {
                continue;
            }

            $this->migrateUp($fqClassName);
        }
    }
}