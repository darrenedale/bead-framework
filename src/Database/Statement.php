<?php

declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Statement as StatementContract;
use Iterator;
use IteratorAggregate;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Traversable;

class Statement implements StatementContract, IteratorAggregate
{
    private PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->statement->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);
    }

    private function bindValue(string|int  $parameter, mixed $value): void
    {
        $previous = null;
        
        try {
            $successful = $this->statement->bindValue($parameter, $value, match (gettype($value)) {
                "integer" => PDO::PARAM_INT,
                "boolean" => PDO::PARAM_BOOL,
                "NULL" => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        } catch (PDOException $err) {
            $previous = $err;
            $successful = false;
        }

        if (!$successful) {
            throw new RuntimeException("Failed to bind value to parameter {$parameter}.", previous: $previous);
        }
    }
    
    public function bindNamedValue(string $parameter, mixed $value): void
    {
        $this->bindValue($parameter, $value);
    }

    public function bindPositionalValue(int $position, mixed $value): void
    {
        $this->bindValue($parameter, $value);
    }
    
    public function execute(?array $params = null): bool
    {
        return $this->statement->execute($params);
    }
    
    public function fetchAll(): array
    {
        try {
            return $this->statement->fetchAll();
        } catch (PDOException $err) {
            throw new RuntimeException("Failed to fetch the data.", previous: $err);
        }
    }
    
    public function fetchNext(): ?array
    {
        $row = $this->statement->fetch();
        return is_array($row) ? $row : null;
    }
    
    public function count(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (PDOException $err) {
            throw new RuntimeException("Failed to fetch row count.", previous: $err);
        }
    }
    
    public function getIterator(): Iterator
    {
        return $this->statement->getIterator();
    }
}
