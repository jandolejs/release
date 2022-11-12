<?php


namespace App\Model;

use App\Configuration;
use App\Exceptions\ReleasePrepareException;
use App\Model\Git\Branch;
use App\Model\Git\Caches;
use App\Model\Git\GitHub;
use App\Presenter;
use App\PullFactory;
use App\ReleaseFactory;
use App\TaskFactory;
use Exception;
use JetBrains\PhpStorm\Pure;
use Nette;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;
use Tracy\ILogger;

class Release
{
    use Nette\SmartObject;

    const STATUS_NEW = 1;
    const STATUS_TESTING = 3;
    const STATUS_FAILING = 5;
    const STATUS_FAILED = 6;
    const STATUS_READY = 7;
    const STATUS_DEPLOYED = 10;

    private int $id;
    private ActiveRow $row;

    public static TaskFactory $taskFactory;
    public static PullFactory $pullFactory;
    public static GitHub $gitHub;

    private array $tasks;

    /**
     * Release constructor.
     * @throws \App\Exceptions\TaskPrepareException
     */
    public function __construct(int $id, ActiveRow $row)
    {
        $this->id    = $id;
        $this->row   = $row;
        $this->tasks = self::$taskFactory->loadByRelease($id);

        if ($this->row->offsetGet('status') !== $this->getStatus())
            $this->setStatus($this->getStatus());

        // Confirm merge by checking github
        if ($this->getStatus() === self::STATUS_DEPLOYED && $this->getMergedAt() === NULL) {
            $this->confirmMerge();
        }
    }

    public function getStatus(bool $string = FALSE): int|string
    {
        $statusOriginal = $this->row->offsetGet('status');

        $status = $statusFromTasks = $this->getStatusFromTasks();

        // Check branch
        if (!$this->getBranch()) $status = self::STATUS_NEW;
        if ($status == self::STATUS_TESTING || ($status == self::STATUS_NEW && $this->getBranch()))
            $status = self::STATUS_TESTING;

        // Check if Pull was created
        if ($this->getPull()) $status = self::STATUS_DEPLOYED;

        // If tasks failed, fail release
        if ($statusFromTasks === self::STATUS_FAILING) $status = self::STATUS_FAILING;

        // If release failed, leave failed
        if ($statusOriginal === self::STATUS_FAILED) $status = self::STATUS_FAILED;

        // Prevent empty release to be prepared
        if ($status === self::STATUS_READY && !$this->hasTasks()) $status = self::STATUS_NEW;

        if ($string) $status = match ($status) {
            self::STATUS_NEW => 'New',
            self::STATUS_TESTING => "Testing",
            self::STATUS_FAILING => 'Will fail',
            self::STATUS_READY => 'Ready',
            self::STATUS_DEPLOYED => 'Deployed',
            self::STATUS_FAILED => 'Failed',
            default => 'status_' . $status,
        };

        return $status;
    }

    private function getStatusFromTasks(): int
    {
        $status        = self::STATUS_NEW;
        $tasksStatuses = array();
        $ready         = TRUE;

        foreach ($this->getTasks() as $task) {
            array_push($tasksStatuses, $task->getStatus());
            if ($task->getStatus() != Task::STATUS_READY) $ready = FALSE;
        }

        // empty release is always new
        if (empty($tasksStatuses)) {
            $status = self::STATUS_NEW;
            $ready  = FALSE;
        }

        // Check if any of tasks failed
        if (array_search(self::STATUS_FAILING, $tasksStatuses) !== FALSE) {
            $status = self::STATUS_FAILING;
        } elseif ($ready) { // if looks ok set ready
            $status = self::STATUS_READY;
            if (!empty($this->_release->pull)) { // if pull, status id deployed
                $status = self::STATUS_DEPLOYED;
            }
        }

        // Check if new release has only tasks ready for deploy
        if ($status === self::STATUS_NEW) {
            $ready = TRUE;
            foreach ($tasksStatuses as $tasksStatus) {
                if ($tasksStatus !== Task::STATUS_READY) $ready = FALSE;
            }
            if ($ready) $status = self::STATUS_READY;
        }

        return $status;
    }

    /**
     * @return Task []
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function getFilenames(): array
    {
        return json_decode($this->row->offsetGet('files')) ?? array();
    }

    public function getCaches(): array
    {
        return Caches::getCaches($this->getFilenames());
    }


    public function hasDangerCaches(): bool
    {
        foreach ($this->getCaches() as $cache => $name) {
            if (in_array($cache, Caches::CACHE_DANGEROUS)) return true;
        }
        return false;
    }

    public function hasInstaller(): bool
    {
        foreach ($this->getFilenames() as $filename) {
            if (preg_match("~".Caches::INSTALLERS_REGEX."~", $filename)) return true;
        }
        return false;
    }

    public function getBranch(): Branch|null
    {
        try {
            return new Branch($this->row->offsetGet('branch') ?? "", $this->getSha());
        } catch (Exception) {
            return NULL;
        }
    }

    public function getSha(): string { return $this->row->offsetGet('sha') ?? ''; }

    public function getPull(): null|int
    {
        return $this->row->offsetGet('pull');
    }

    #[Pure] public function hasTasks(): int { return count($this->getTasks()); }

    private function setStatus($status_id)
    {
        $this->row->update(["status" => $status_id]);
    }

    public function getId(): int { return $this->id; }

    public function getDeployLink()
    {
        $url = 'https://app.buddy.works/goodform/goodform/pipelines/pipeline/418005/trigger-webhook';
        $data = [
            'token' => Configuration::get("release/deploy/token"),
            'branch' => $this->getBranchName(),
        ];

        return $url . '?' . http_build_query($data);
    }

    /**
     * Delete branch, tasks, ... in this release
     * @throws Exception
     */
    public function failed(): void
    {
        // delete github branch
        $branch = $this->getBranch();
        if ($branch) {
            self::$gitHub->deleteBranch($branch);
            $this->row->update(['branch' => '']);
        }

        $this->setStatus(self::STATUS_FAILED);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getDiffLink(): null|string
    {
        return self::$gitHub->getDiffLink($this->getBranch());
    }

    public function getMergedAt()
    {
        return $this->row->offsetGet('merged_at');
    }

    /** @noinspection PhpUnused */
    public function getWarnings(): array
    {
        $warnings = array();

        if ($this->hasDangerCaches()) $warnings[] = "Caches";
        if ($this->hasInstaller()) $warnings[] = 'Installer';

        return array_unique($warnings);
    }

    /** @noinspection PhpUnused */
    public function getDeployedAt(): null|string
    {
        if ($this->getMergedAt() !== null) {
            $deployed = $this->getMergedAt();
        } else {
            $deployed = $this->row->offsetGet('deployed_at');
        }

        return $deployed;
    }

    public function getPullLink(): null|string
    {
        return self::$gitHub->getPullLink($this->getPull());
    }

    public function getName(): string
    {
        $tasks = "";
        foreach ($this->getTasks() as $task) {
            $tasks .= " " . $task->getPZ(TRUE);
        }

        return sprintf(Configuration::get("release/pull/name"), $this->getId(), $tasks);
    }

    /**
     * @throws Exception
     */
    public function prepare(array $pulls): self
    {
        $errors = array();

        // Create branch and Fetch details from GitHub
        $this->setUpBranch();
        $this->pulls2tasks($pulls);

        // Load new tasks
        $this->tasks = self::$taskFactory->loadByRelease($this->getId());

        // Check tasks if all are clean to merge
        // TODO if forced release was created, mark it as forced and disable making PR, etc, ...
        if (empty($errors)) $this->checkTasks($errors);

        // If NO error so far, try to merge tasks
        if (empty($errors)) $this->mergeTasks($errors);

        // Fetch files and find out w
        try {
            $filenames = $this->fetchFiles();
            $this->row->update([
                'files' => json_encode($filenames),
                'cache_flush' => json_encode(Caches::getCaches($filenames)),
            ]);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        // If NO error occurred, fail preparing
        if (!empty($errors)) { // Change status and return release
            // Revert changes
            $this->deleteBranch();
            $this->deleteTasks();
            $this->setStatus(self::STATUS_FAILED);
            $this->row->delete();

            // Prepare fail message
            $message = "Preparing release failed. Some tasks are missing approve, or checks on them are not finished or are failing.\n";
            foreach ($errors as $error) $message .= "\n- " . $error;

            // Fail preparing
            throw new ReleasePrepareException($message);
        }

        $this->changeStatus(self::STATUS_TESTING);
        return $this;
    }

    private function mergeTasks(&$errors)
    {
        foreach ($this->getTasks() as $task) {
            try {
                $sha = self::$gitHub->mergeTask($task, $this->getBranch());
                $this->row->update(['sha' => $sha]); // Update sha of this release
            } catch (Exception $e) {
                $errors[] = "Pull [#{$task->getPull()}]({$task->getPullLink()}) failed: " . $e->getMessage();
            }
        }
    }

    private function checkTasks(&$errors, bool $deploying = false)
    {
        foreach ($this->getTasks() as $task) {

            if ($deploying && !in_array(Configuration::get('pull/label/ready'), $task->getLabels(true))) {
                $errors[] = "Pull [#{$task->getPull()}]({$task->getPullLink()}) failed. Tested and ready label is not present on it.";
            }

            if (in_array(Configuration::get('pull/label/prevent'), $task->getLabels(true))) {
                $errors[] = "Pull [#{$task->getPull()}]({$task->getPullLink()}) failed. Do not merge label has blocked adding it to this release.";
            }

            // If task is not ready to be tested, say why
            if (!$task->isCleanToMerge()) {

                // Prepare error message
                $error = "Pull [#{$task->getPull()}]({$task->getPullLink()}) failed (mergeable: " . ($task->isMergeable() ? 'true' : 'false');
                $error .= " ,status:{$task->getMergeableStatus()})"; // add status of task

                // Add hints
                if ($task->isMergeable() && $task->getMergeableStatus() == 'blocked') $error .= " It probably does not have approve.";
                if (!$task->isMergeable() && $task->getMergeableStatus() == 'dirty') $error .= " It probably have conflicts with " . Configuration::get('github/master') . " branch.";
                if ($task->getMergeableStatus() == 'unknown') $error .= " Maybe checks are not confirmed. Wait and try again or check it [here]({$task->getPullLink()}/checks).";

                // Let not approved tasks if creating release, but prevent creating PR
                if (!$deploying && $task->isMergeable() && $task->getMergeableStatus() == 'blocked') {
                    $task->getRow()->update(['approve' => false]);
                } else {
                    $errors[] = $error;
                }
            }
        }
    }

    /**
     * Fetch new data from github
     * @param array $pulls
     * @throws Exception
     */
    public function pulls2tasks(array $pulls)
    {
        $tasksData = array();

        // Create tasks in database
        foreach ($pulls as $pull) {

            // Fetch data from GitHub
            $pull = self::$pullFactory->load($pull);
            $pull->fetch();
            $data = $pull->getArray($this->getId());

            // Add it to tasks to be saved
            $tasksData[] = $data;
        }

        // Save and Load newly fetched tasks
        self::$taskFactory->getTable()->insert($tasksData);
    }

    /**
     * @throws Exception
     */
    public function deleteBranch(): self
    {
        if ($this->getBranch())
            self::$gitHub->deleteBranch($this->getBranch());

        $this->row->update(['branch' => '', 'sha' => '',]);
        return $this;
    }

    public function deleteTasks(): self
    {
        foreach ($this->getTasks() as $task) {
            $task->delete(); // Delete tasks
        }

        return $this;
    }

    /**
     * Check if PR was merged, if so than save merge time
     */
    public function confirmMerge()
    {
        try {
            $pull = self::$pullFactory->load($this->getPull());

            // Check if PR was merged AND if merge time is there
            if ($pull->fetch()->merged !== true || $pull->fetch()->merged_at === null) return;

            $mergedAt = date("Y-m-d H:i:s", strtotime($pull->fetch()->merged_at));
            $this->row->update(['merged_at' => $mergedAt]);
        } catch (Exception $e) {
            Debugger::log($e, ILogger::WARNING);
            return;
        }
    }

    /**
     * Check if PR was merged, if so than save merge time
     * @throws Exception
     */
    public function fetchFiles(): array
    {
        $hash = $this->row->offsetGet('base_sha') . "..." . $this->getSha();
        $result = self::$gitHub->call("compare/$hash"); // {base}...{head}

        $filenames = array();
        foreach ($result->files as $file) {
            $filenames[] = $file->filename;
        }

        return $filenames;
    }

    /**
     * Prepare branch's name like release/254
     */
    public function getBranchName(): string
    {
        return Configuration::get('release/branch/prefix') . "/" . $this->getId();
    }

    /**
     * Check tasks if any of them has manual action
     */
    public function hasManual(): bool
    {
        foreach ($this->tasks as $task) {
            if ($task->hasManualAction()) return true;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function setUpBranch(): Branch
    {
        $name = $this->getBranchName();

        // Create branch and remember it
        $branch = self::$gitHub->createBranch($name);
        $this->row->update([
            'branch'   => $branch->getName(),
            'sha'      => $branch->getSha(),
            'base_sha' => $branch->getSha(),
        ]);

        // Return newly created branch
        return $branch;
    }

    public function changeStatus(int $status): self
    {
        $this->row->update(['status' => $status]);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function checkActual(): self
    {
        $changed = [];

        // Merge tasks in release again to see if any new content was added after release
        foreach ($this->tasks as $task) {
            // Merge tasks branch again to find result of commit
            $head   = new Branch($task->getBranch(), $task->getSha());
            $base   = $this->getBranch();
            $result = self::$gitHub->merge($base, $head);

            // If new commit did something, it means there is new content in tasks branch
            if ($result !== true) {
                $changed[$task->getPull()] = $task;
            }
        }

        // If there is new content in branch, it means this release is no longer actual
        if (!empty($changed)) {
            // Fail this release
            $this->deleteBranch();
            $this->setStatus(self::STATUS_FAILED);

            // Create failing message
            $message = "PR ahead of this release: ";
            foreach ($changed as $pullNo => $task) {
                $task->addNote("Ahead of this release!");
                $link = $task->getPullLink();
                $message .= "#[$pullNo]($link) ";
            }
            $message .= "| Please create new release";

            // Fail this action
            throw new Exception($message);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function createPull(): self
    {
        $errors = array();

        // Re-fetch tasks
        $tasksData = array();
        foreach ($this->getTasks() as $task) {

            // Fetch data from GitHub
            $pull = $task->getPull();
            $pull = self::$pullFactory->load($pull);
            $pull->fetch();
            $data = $pull->getArray($this->getId());

            // Add it to tasks to be saved
            $tasksData[] = $data;
        }

        // Save and Load newly fetched tasks
        $this->deleteTasks();
        self::$taskFactory->getTable()->insert($tasksData);
        $this->tasks = self::$taskFactory->loadByRelease($this->getId());

        // CHeck new tasks for merge
        $this->checkTasks($errors, true);

        if (!empty($errors)) {

            // Fail release
            $this->failed();

            // Throw exception
            $message = "Preparing pull failed.\n";
            foreach ($errors as $error) $message .= "\n- " . $error;
            throw new Exception($message);
        }

        $pull = self::$pullFactory->create($this);

        // Add label if PR with manual action is inside
        if ($this->hasManual()) {self::$gitHub->addManualLabel($pull); }

        $this->row->update([
            'status'      => self::STATUS_READY,
            'pull'        => $pull,
            'deployed_at' => new DateTime(),
        ]);

        return $this;
    }

    /**
     * Prepare release body with pull requests inside, caches to flush,  etc...
     */
    public function getBody(): string
    {
        $body = "";

        // Add important info
        //$body .= "<h2>Release number " . $this->getId() . "</h2>";
        if ($this->hasInstaller()) $body .= "\n<span><img src='https://via.placeholder.com/15/f03c15/000000?text=+' alt='!'> Installers inside!</span>\n";
        if ($this->hasDangerCaches()) $body .= "\n<span>\n\t<img src='https://via.placeholder.com/15/f03c15/000000?text=+' alt='!'>\n\tDanger caches inside\n</span>\n";

        // Add table with pull requests
        $pullsTable = "";
        foreach ($this->getTasks() as $task) {
            $pullsTable .= $task->getPullRow();
        }
        $body .= "\n<h2>Tasks inside this Pull:</h2>\n<table>$pullsTable\n</table>";

        // Add caches
        $body .= "\n\n" . Caches::markdownCaches($this->getFilenames());

        return $body;
    }
}
