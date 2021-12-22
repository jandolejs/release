<?php

namespace App;

use App\Exceptions\ReleasePrepareException;
use App\Exceptions\TaskPrepareException;
use App\Model\Git\GitHub;
use App\Model\Release;
use Nette\Database\Explorer;
use Nette\Security\User;
use Throwable;

class ReleaseFactory extends DatabaseObject
{
    private array $releases = []; // cache for releases
    public static GitHub $gitHub;
    private User $user;
    public static bool $forceCreate = false;

    public function __construct(Explorer $database, User $user)
    {
        parent::__construct($database, Configuration::get('release/table'));
        $this->user = $user;
    }

    /**
     * Create new release
     * @throws \Throwable
     */
    public function create(array $pulls): Release
    {
        $this->database->beginTransaction();
        $pulls = array_unique($pulls);

        try {
            // Try insert new release to database
            $id = $this->getTable()->insert([
                'status' => Release::STATUS_NEW,
                'user_id' => $this->user->getId(),
            ])->getIterator()->current();

            // Try load this release
            $release = $this->load($id);
            try {
                $release->prepare($pulls);
            } catch (Throwable $e){
                $release->deleteBranch();
                throw $e;
            }

            // Try commit transaction to database after all necessary dependencies was created (branch, etc...)
            $this->database->commit();

            return $release; // Return release

        } catch (Throwable $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    /**
     * Load release by ID
     * @param int $id ID of release
     * @return Release
     * @throws ReleasePrepareException|TaskPrepareException
     */
    public function load(int $id): Release
    {
        // cache releases
        if (!isset($this->releases[$id])) {

            // load row from database
            $row = $this->getTable()->get($id);
            if (!$row) throw new ReleasePrepareException("Release $id not found!");

            // load release
            $release = new Release($id, $row);

            // save it
            $this->releases[$id] = $release;
        }

        // return release
        return $this->releases[$id];
    }
}
