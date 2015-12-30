<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core;

use Laravel\Lumen\Application;
use Monolog\Handler\SyslogUdpHandler;

class SpiraApplication extends Application
{
    /**
     * Get the Monolog handler for the application.
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected function getMonologHandler()
    {
        // @codeCoverageIgnoreStart
        if (env('LOG_UDP_HOST')) {
            return new SyslogUdpHandler(env('LOG_UDP_HOST'), env('LOG_UDP_PORT'));
        }

        return parent::getMonologHandler();
        // @codeCoverageIgnoreEnd
    }

}
