<?php

/**
 * The EmptyTrash Cron task
 *
 * @package  Nails\Cdn
 * @category Task
 */

namespace Nails\Cdn\Cron\Task\Trash;

use Nails\Cron\Task\Base;

/**
 * Class EmptyTrash
 *
 * @package Nails\Cdn\Cron\Task\Trash
 */
class EmptyTrash extends Base
{
    /**
     * The task description
     *
     * @var string
     */
    const DESCRIPTION = 'Removes old items from the trash';

    /**
     * The cron expression of when to run
     *
     * @var string
     */
    const CRON_EXPRESSION = '0 0 * * *';

    /**
     * The console command to execute
     *
     * @var string
     */
    const CONSOLE_COMMAND = 'cdn:trash:empty';
}
