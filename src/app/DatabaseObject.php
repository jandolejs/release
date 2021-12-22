<?php

namespace App;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

abstract class DatabaseObject
{
    protected Explorer $database;
    private string $table;

    public function __construct(Explorer $database, string $table)
    {
        $this->table = $table;
        $this->database = $database;
    }

    public final function getTable(?string $name = NULL): Selection
    {
        return $this->getDatabase()->table($name ?? $this->table);
    }

    protected final function getDatabase(): Explorer
    {
        return $this->database;
    }
}
