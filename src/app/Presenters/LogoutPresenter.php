<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Presenter;
use JetBrains\PhpStorm\NoReturn;

/**
 * Class LogoutPresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class LogoutPresenter extends Presenter
{

    /**
     * @throws \Nette\Application\AbortException
     */
    #[NoReturn] public function startup()
    {
        parent::startup();

        $this->getUser()->logout();
        $this->flashMessage("You were logged out", "success");

        $this->redirect("Homepage:");
    }
}
