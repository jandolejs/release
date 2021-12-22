<?php


namespace App\Exceptions;


use Exception;
use stdClass;

class GitApiCallException extends Exception
{
    private stdClass $full;
    private array $call;

    public function __construct($message, stdClass $full, array $call) {
        parent::__construct($message);

        $this->full = $full;
        $this->call = $call;
    }

    public function getCall(): array|null
    {
        return $this->call ?? null;
    }

    public function getFull(): stdClass|null
    {
        return $this->full ?? null;
    }
}
