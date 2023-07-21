<?php

/**
 * The MomitorUnused Cron task
 *
 * @package  Nails\Cdn
 * @category Task
 */

namespace Nails\Cdn\Cron\Task\Monitor;

use Nails\Cron\Task\Base;

/**
 * Class Unused
 *
 * @package Nails\Cdn\Cron\Task\Monitor
 */
class Unused extends Base
{
    /**
     * The cron expression of when to run
     *
     * @var string
     */
    const CRON_EXPRESSION = '30 1 * * *';

    /**
     * The console command to execute
     *
     * @var string
     */
    const CONSOLE_COMMAND = 'cdn:monitor:unused';

    /**
     * The arguments to pass to the console command
     *
     * @var string[]
     */
    const CONSOLE_ARGUMENTS = ['--force'];
}
