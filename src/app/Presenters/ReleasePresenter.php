<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Configuration;
use App\Exceptions\GitHubException;
use App\Model\Git\GitHub;
use App\Model\Release;
use App\Presenter;
use App\ReleaseFactory;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Nette\DI\Attributes\Inject;
use Throwable;
use Tracy\Debugger;

/**
 * Class ReleasePresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class ReleasePresenter extends Presenter
{
    #[Inject] public ReleaseFactory $releaseFactory;
    #[Inject] public GitHub $gitHub;

    /**
     * Check user before accessing
     * @throws \Nette\Application\AbortException
     */
    public function startup()
    {
        parent::startup();

        $this->permit('release');
    }


    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionLatest(): void
    {
        $this->permit('release', 'show');

        $last = $this->database->table(Configuration::get('release/table'))->max('id');
        if ($last === NULL) $this->redirect("Release:create");

        $this->redirect("Release:show", $last);
    }

    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionCreate(array $pulls): void
    {
        $this->permit('release', 'create');

        // Check pull requests
        if (empty($pulls)) {
            $this->flashMessage("Pulls empty", 'danger');
            $this->redirect('Release:new');
        }

        // Make it possible to force creating release
        if ($this->getParameter('force') === 'on') ReleaseFactory::$forceCreate = true;

        // Create release
        try {
            $id = $this->releaseFactory
                ->create($pulls)
                ->getId();

            $this->statistics->create('release', 'create', $id);

            // Add statistics
            foreach ($pulls as $pull) {
                $this->statistics->create('task', 'import', $pull);
            }

            $this->flashMessage("Release $id was created", 'success');

        } catch (Throwable $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect("Release:");
        }

        // Go to release detail
        $this->redirect("Release:show", $id);
    }

    /**
     * @param int $id
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionFail(int $id): void
    {
        $this->permit('release', 'fail');

        try {
            $this->releaseFactory
                ->load($id)
                ->failed();
            $this->flashMessage("Release was failed.", "success");
        } catch (Exception $e) {
            $this->flashMessage($e->getMessage(), "danger");
        }

        $this->redirect("Release:");
    }


    /**
     * @param int $id
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function actionCreatePull(int $id): void
    {
        $this->permit('github', 'createPull');

        try {
            $release = $this->releaseFactory
                ->load($id);
            $pullLink = $release
                ->checkActual()
                ->createPull()
                ->getPullLink();

            $this->flashMessage("Pull request created - [GitHub - Pull request #{$release->getPull()}]($pullLink)", "success");
        } catch (Exception $e) {
            $this->flashMessage($e->getMessage(), "danger");
        }

        $this->redirect("Release:show", $id);
    }


    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function renderDefault(): void
    {
        $this->permit('release', 'list');

        $releases = array();
        $lastReleaseId = (int)$this->getParameter('lastReleaseId');

        try {
            // Opened releases limit 10
            $ids = $this->database->table('releases')->select('id')->order("id DESC")->where('status NOT IN ', [Release::STATUS_FAILED, Release::STATUS_DEPLOYED])->limit(10)->fetchAll();
            foreach ($ids as $id => $data) {
                $releases['opened'][] = $this->releaseFactory->load($id);
            }
            // All releases limit 10
            $ids = $this->database->table('releases')->select('id')->order("id DESC")->limit(10)->fetchAll();
            foreach ($ids as $id => $data) {
                $releases['all'][] = $this->releaseFactory->load($id);
            }
        } catch (Exception $e) {
            Debugger::log($e);
            $this->flashMessage("Error occurred", 'danger');
            $this->redirect("Homepage:");
        }

        $this->template->lastReleaseId = $lastReleaseId; // when you go back, this says it to template
        $this->template->releases      = $releases;
    }


    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function renderDeployed(): void
    {
        $this->permit('release', 'list');
        $releases = array();

        // Last 100 deployed releases
        $ids = $this->database->table('releases')->select('id')->order("id DESC")->where('status IN ', [Release::STATUS_DEPLOYED])->limit(100)->fetchAll();
        try {
            foreach ($ids as $id => $data) {
                $releases[] = $this->releaseFactory->load($id);
            }
        } catch (Exception $e) {
            Debugger::log($e);
            $this->flashMessage("Error occurred", 'danger');
            $this->redirect("Release:");
        }

        $this->template->releases = $releases;
    }

    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    public function renderNew()
    {
        $this->permit('release', 'list');

        try {
            $this->template->pulls = $this->gitHub->getPulls();
        } catch (GitHubException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect("Release:");
        }
    }

    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    #[NoReturn] public function renderShow(int $id): void
    {
        $this->permit('release', 'show');

        try {
            $release = $this->releaseFactory->load($id);
        } catch (Exception $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect("Release:");
        }

        $this->template->release = $release;
    }
}
