<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;


/**
 * Class RouterFactory
 * @noinspection PhpUnused
 */
final class RouterFactory
{
    use Nette\StaticClass;

    /**
     * @noinspection PhpUnused
     */
    public static function createRouter(): RouteList
    {
        $router = new RouteList;
        $router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
        return $router;
    }
}
