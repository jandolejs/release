<?php


namespace App;

use Nette\Security\Authorizator;

/**
 * Class MyAuthorizer
 * @package App
 * @noinspection PhpUnused
 */
class MyAuthorizer implements Authorizator
{

    const PERMISSIONS = [
        'user' => [
            'release' => [
                'show', 'create', 'list'
            ],
            'github'  => [
                'pulls'
            ],
            'task' => [
                'show', 'note'
            ],
        ],
        'power_user' => [
            'release' => [
                'deploy', 'fail'
            ],
            'task' => [
                'approve', 'fail', 'import'
            ],
        ],
    ];

    public function isAllowed($role, $resource, $privilege): bool
    {
        //if ($role === 'admin') return TRUE;

        if (isset(self::PERMISSIONS[$role])) {
            if (isset(self::PERMISSIONS[$role][$resource])) {

                // no privilege wanted
                if (empty($privilege)) return TRUE;

                // privilege exists
                if (in_array($privilege, self::PERMISSIONS[$role][$resource]))
                    return TRUE;
            }
        }

        return FALSE;
    }
}