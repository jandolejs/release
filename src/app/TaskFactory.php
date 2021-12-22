<?php

namespace App;

use App\Exceptions\LoadException;
use App\Exceptions\TaskPrepareException;
use App\Model\Task;
use Exception;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class TaskFactory extends DatabaseObject
{

    /**
     * TaskFactory constructor.
     */
    public function __construct(Explorer $database)
    {
        parent::__construct($database, Configuration::get('task/table'));
    }

    /**
     * @throws \Exception
     */
    public function loadById(int $id): Task
    {
        // fetch data about task
        $fetched = $this->getTable()->where('id = ?', $id)->fetch();

        // check if task really exists
        if (!$fetched instanceof ActiveRow)
            throw new LoadException("Task with id '$id' not found");

        // return Task instance
        return new Task($fetched);
    }

    /**
     * Load all tasks by release number
     * @param int $id Release number
     * @return Task[] Array of tasks
     * @throws TaskPrepareException
     */
    public function loadByRelease(int $id): array
    {
        // fetch all rows of tasks
        $rows  = $this->getTable()->where('release = ?', $id)->order('status, id DESC')->fetchAll();
        $tasks = array();

        // load tasks
        foreach ($rows as $fetched) {
            try {
                $pull = $fetched->offsetGet('pull');
                $tasks[$pull] = new Task($fetched);
            } catch (Exception $e) {
                throw new TaskPrepareException("Tasks failed to load. {$e->getMessage()}");
            }
        }

        // return array of loaded tasks
        return $tasks;
    }
}
