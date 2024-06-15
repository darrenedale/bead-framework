<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Column as DatabaseColumnContract;
use Bead\Contracts\Database\ForeignKey as DatabaseForeignKeyContract;
use Bead\Contracts\Database\ForeignKey as DatabaseForiegnKeyContract;
use Bead\Contracts\Database\Index as DatabseIndexContract;
use Bead\Contracts\Database\Table as DatabaseTableContract;

class Table implements DatabaseTableContract
{
    private string $name;

    /** @var DatabaseColumnContract[] */
    private array $columns;

    /** @var DatabseIndexContract[] */
    private array $indices;

    /** @var DatabseIndexContract[] */
    private array $foreignKeys;

    private ?string $characterSet;

    private ?string $collation;

    private ?string $comment;

    public function __construct(string $name, string $characterSet = null, string $collation = null)
    {
        $this->columns = [];
        $this->indices = [];
        $this->characterSet = $characterSet;
        $this->collation = $collation;
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function withColumn(DatabaseColumnContract $column): self
    {
        $clone = clone $this;
        $clone->columns[] = $column;
        return $clone;
    }

    public function indices(): array
    {
        return $this->indices;
    }

    public function withIndex(DatabaseIndexContract $index): self
    {
        $clone = clone $this;
        $clone->indices[] = $index;
        return $clone;
    }

    public function characterSet(): ?string
    {
        return $this->characterSet;
    }

    public function withCharacterSet(?string $charset): self
    {
        $clone = clone $this;
        $clone->characterSet = $charset;
        return $clone;
    }

    public function collation(): ?string
    {
        return $this->collation;
    }

    public function withCollation(?string $collation): self
    {
        $clone = clone $this;
        $clone->collation = $collation;
        return $clone;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function withComment(?string $comment): self
    {
        $clone = clone $this;
        $clone->comment = $comment;
        return $clone;
    }

    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function withForeignKey(ForeignKey $key): self
    {
        $clone = clone $this;
        $clone->foreignKeys[] = $key;
        return $clone;
    }
}