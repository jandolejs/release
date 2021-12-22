<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Git\GitHub;
use App\Presenter;
use Nette\DI\Attributes\Inject;

/**
 * Class ModalPresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class ModalPresenter extends Presenter
{
    #[inject] public GitHub $gitHub;
}
