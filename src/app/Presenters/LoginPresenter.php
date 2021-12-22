<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Presenter;
use Exception;
use Nette;


/**
 * Class LoginPresenter
 * @package App\Presenters
 * @noinspection PhpUnused
 */
class LoginPresenter extends Presenter
{

    /**
     * @throws \Nette\Application\BadRequestException|\Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    public function actionUsername()
    {
        $username = $this->getParameter('username');
        $password = $this->getParameter('password');

        try {
            if (empty($username) || empty($password)) throw new Exception("Login or password is empty");

            $this->getUser()->login($username, $password);

            $this->statistics->create("user", 'login_success', $username);
        } catch (Exception $e) {
            $this->statistics->create("user", 'login_fail', $username);

            $this->error($e->getMessage(), Nette\Http\IResponse::S401_UNAUTHORIZED);
        }

        $this->terminate();
    }

    /**
     * @throws \Nette\Application\AbortException
     * @noinspection PhpUnused
     */
    public function renderDefault()
    {
        if (isset($_POST['login-form-sent'])) {
            try {
                $username = $_POST['username'] ?? NULL;
                $password = $_POST['password'] ?? NULL;

                if (empty($username) || empty($password)) throw new Exception("Login or password is empty");

                $this->getUser()->login($username, $password);
                $this->flashMessage("Successfully Logged in", "success");
                $this->statistics->create("user", 'login_success', $username);
            } catch (Exception $e) {
                $this->flashMessage($e->getMessage(), "danger");
                $this->statistics->create("user", 'login_fail', $username);
                $this->redirect("Login:");
            }

            $this->redirect("Homepage:");
        }
        if (isset($_POST['username'])) $this->template->username = $_POST['username'];
    }
}
