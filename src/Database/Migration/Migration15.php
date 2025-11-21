<?php

/**
 * Migration:   15
 * Started:     21/11/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Interfaces;
use Nails\Common\Traits;

class Migration15 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    // --------------------------------------------------------------------------

    public function execute(): void
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_token` ADD INDEX idx_expires (expires);');
    }
}
