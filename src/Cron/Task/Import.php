<?php

/**
 * The Import Cron task
 *
 * @package  Nails\Cdn
 * @category Task
 */

namespace Nails\Cdn\Cron\Task;

use Nails\Cron\Task\Base;

/**
 * Class Import
 *
 * @package Nails\Cdn\Cron\Task
 */
class Import extends Base
{
    /**
     * The cron expression of when to run
     *
     * @var string
     */
    const CRON_EXPRESSION = '* * * * *';

    /**
     * The console command to execute
     *
     * @var string
     */
    const CONSOLE_COMMAND = 'cdn:import';
}
