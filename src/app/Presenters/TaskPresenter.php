<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Presenter;
use App\TaskFactory;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Nette\DI\Attributes\Inject;

/**
 * Class TaskPresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class TaskPresenter extends Presenter
{
    #[inject] public TaskFactory $taskFactory;

    /**
     * Check user before accessing
     * @throws \Nette\Application\AbortException
     */
    public function startup()
    {
        parent::startup();

        $this->permit('task');
    }


    /**
     * Mark task as tested and ready for deploy
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionApprove(int $id): void
    {
        $this->permit('task', 'approve');

        try {
            $task = $this->taskFactory->loadById($id);
            $task->approve();
        } catch (Exception $e) {
            $this->flashMessage("Changing status failed: " . $e->getMessage(), "danger");
            $this->redirect('Release:');
        }

        $this->statistics->create('task', 'approve', $task->getPull());
        $this->flashMessage("Pull request #{$task->getPull()} was marked as ready.", "success");
        $this->redirect('Release:show', $task->getReleaseNo());
    }

    /**
     * Mar task as failed after testing
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionFailed(int $id): void
    {
        $this->permit('task', 'fail');

        try {
            $task = $this->taskFactory->loadById($id);
            $task->failed();
        } catch (Exception $e) {
            $this->flashMessage("Changing status failed: " . $e->getMessage(), "danger");
            $this->redirect('Release');
        }

        $this->flashMessage("Pull #{$task->getPull()} marked as fail.", "success");
        $this->statistics->create('task', 'failed', $task->getPull());
        $this->redirect('Release:show', $task->getReleaseNo());
    }

    /**
     * Add note to task
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionUpdateNote(): void
    {
        $this->permit('task', 'note');
        $id = (int) $this->getParameter('task');

        try {
            $task = $this->taskFactory->loadById($id);

            $task->addNote($this->getParameter('note'));
        } catch (Exception $e) {
            $this->flashMessage("Changing status failed: " . $e->getMessage(), "danger");
        }

        $this->terminate();
    }


    /**
     * Prevent rendering anything
     * @throws \Exception
     */
    #[NoReturn] public function beforeRender(): void
    {
        $this->redirect("Homepage:");
    }
}
