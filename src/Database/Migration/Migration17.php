<?php

/**
 * Migration:   17
 * Started:     21/11/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

class Migration17 extends Migration16
{
    /**
     * Applications moving from `pre-new-admin` to `develop`
     *
     * The common migration is 13, pre-new-admin is on 15, develop is on 17.
     *
     * In order to ensure that all develop migrations run as expected
     * they have been [re]written to be safe to run multiple times. Each one
     * of these migrations extend the previous one so that the chain of
     * migrations all happen, but run multiple times (Safely))
     */

    public function execute(): void
    {
        parent::execute();
        $oResult = $this->query('SHOW INDEX FROM `{{NAILS_DB_PREFIX}}cdn_token` WHERE Key_name = "idx_expires"');
        if ($oResult->rowCount() === 0) {
            $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_token` ADD INDEX idx_expires (expires);');
        }
    }
}
