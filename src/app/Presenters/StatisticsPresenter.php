<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Git\GitHub;
use App\Presenter;
use Nette\DI\Attributes\Inject;

/**
 * Class StatisticsPresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class StatisticsPresenter extends Presenter
{
    #[inject] public GitHub $github;

    /**
     * @throws \Nette\Application\AbortException
     */
    public function startup()
    {
        parent::startup();

        $this->permit('statistics');
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    public function renderDefault()
    {
        $this->permit('statistics', 'show');

        $statistics = $this->statistics;

        $this->template->githubRate = $this->github->getApiRate();
        $this->template->statistics = $statistics->getAllStatisticsTogether();
    }
}
