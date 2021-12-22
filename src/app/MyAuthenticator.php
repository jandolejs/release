<?php


namespace App;

use Exception;
use Nette;
use Nette\Security\SimpleIdentity;


/**
 * Class MyAuthenticator
 * @package App
 * @noinspection PhpUnused
 */
class MyAuthenticator implements Nette\Security\Authenticator
{
    private Nette\Database\Explorer $database;
    private Nette\Security\Passwords $passwords;

    public function __construct(Nette\Database\Explorer $database, Nette\Security\Passwords $passwords)
    {
        $this->database  = $database;
        $this->passwords = $passwords;
    }

    /**
     * @throws \Exception
     */
    public function authenticate(string $user, string $password): SimpleIdentity
    {
        $row = $this->database->table(Configuration::get('users/table'))
            ->where('username', $user)
            ->fetch();

        if (!$row) throw new Nette\Security\AuthenticationException('User not found!');
        if (!$row->offsetGet('active')) throw new Exception("User is not active!");
        if (!$row->offsetGet('role')) throw new Exception("User has no role!");
        if (!$row->offsetGet('password')) throw new Exception("User has no password set!");

        if (!$this->passwords->verify($password, $row->offsetGet('password'))) {
            throw new Nette\Security\AuthenticationException('Invalid password.');
        }

        return new SimpleIdentity(
            $row->offsetGet('id'),
            explode(",", $row->offsetGet('role')),
            [
                'username' => $row->offsetGet('username'),
                'image'    => $row->offsetGet('image'),
                'locale'   => $row->offsetGet('locale'),
                'name'     => $row->offsetGet('name'),
            ]
        );
    }
}
