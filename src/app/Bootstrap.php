<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;


class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;
        $appDir       = dirname(__DIR__);

        $configurator->setDebugMode( // allow tracy for localhost connections
            str_ends_with($_SERVER['HTTP_HOST'] ?? '', ".localhost")
        );
        $configurator->enableTracy('/tmp');

        $configurator->setTimeZone('Europe/Prague');
        $configurator->setTempDirectory('/tmp/app');

        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register();

        $configurator->addConfig($appDir . '/config/common.neon');
        $configurator->addConfig($appDir . '/config/usernames.neon');

        return $configurator;
    }
}
