<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Git\Caches;
use App\Model\Git\GitHub;
use App\Presenter;
use App\PullFactory;
use App\ReleaseFactory;
use Nette\DI\Attributes\Inject;
use Nette\Utils\DateTime;

/**
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class ToolsPresenter extends Presenter
{

    #[inject] public PullFactory $pullFactory;
    #[inject] public ReleaseFactory $releaseFactory;

    /**
     * Check user before accessing
     * @throws \Nette\Application\AbortException
     */
    public function startup()
    {
        parent::startup();

        $this->permit('tools');
    }

    /**
     * @noinspection PhpUnused
     */
    /**
     * @throws \App\Exceptions\TaskPrepareException
     * @throws \App\Exceptions\ReleasePrepareException
     * @throws \Nette\Application\AbortException
     */
    public function actionCaches()
    {
        if ($this->getParameter('pull') !== null) {
            $pull = $this->getParameter('pull');

            if (!preg_match("~(\d+)$~", $pull, $matches)) {
                $this->flashMessage("Parse problem", "warning");
                $this->redirect("Tools:");
            }

            $pull = (int)$matches[1];

            // prepare caches
            $filenames = $this->pullFactory->getFilenames($pull);
        } elseif ($this->getParameter('release') !== null) {
            $release = (int) $this->getParameter('release');
            $release = $this->releaseFactory->load($release);
            $filenames = $release->getFilenames();
        } else {
            $this->flashMessage("Problem occurred (release or pull param are missing)", "warning");
            $this->redirect("Tools:");
        }
        $caches = Caches::getCaches($filenames);

        // caches per file for all files
        $perFile = array();
        foreach ($filenames as $filename) {
            $perFile[$filename] = Caches::getCaches([$filename]);
        }

        // files for caches
        $perCache = array();
        foreach ($perFile as $filename => $fileCaches) {
            foreach ($fileCaches as $cache) {
                $perCache[$cache][] = $filename;
            }
        }

        $this->template->caches = $caches;
        $this->template->perFile = $perFile;
        $this->template->perCache = $perCache;
        $this->template->pull = $pull ?? 0;
    }

    /**
     * @throws \Exception
     */
    public function actionTi()
    {

        $week = date('w');
        $from = date('Y-m-d', strtotime('-' . $week . ' days'));
        $to = date('Y-m-d', strtotime('+' . (6 - $week) . ' days'));
        $pullList = $this->gitHub->getPullArray(GitHub::PULL_STATE_CLOSED, $from, $to);
        $errors = array();
        $pulls = array();

        foreach ($pullList as $number => $pull) {
            if (str_starts_with($pull->title, "Release ")) continue; // skip releases
            $body = $pull->body;
            $pulls[$number]['std'] = $pull; // add to all pulls
            $pulls[$number]['text'] = $body;


            if (!strpos($body, "## Test instructions")) {
                $errors[] = "No test instructions defined";
            }

            preg_match("~(?<=## Test instructions[[:cntrl:]])(<!--.+-->)?.+?(?:(?=[[:cntrl:]]##)|$)~s", $body, $matches);
            bdump($matches[0], (string)$number);


            $pulls[$number]['errors'] = $errors;
        }

        $this->template->pulls = $pulls;
    }
}
