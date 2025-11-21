<?php

/**
 * The Housekeeping Tokens Cron task
 *
 * @package  Nails\Cdn
 * @category Task
 */

namespace Nails\Cdn\Cron\Task\Housekeeping;

use Nails\Cron\Task\Base;

/**
 * Class Import
 *
 * @package Nails\Cdn\Cron\Task\Housekeeping
 */
class Tokens extends Base
{
    /**
     * The cron expression of when to run
     *
     * @var string
     */
    const CRON_EXPRESSION = '*/15 * * * *';

    /**
     * The console command to execute
     *
     * @var string
     */
    const CONSOLE_COMMAND = 'cdn:housekeeping:tokens';
}
