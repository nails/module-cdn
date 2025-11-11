<?php

/**
 * Migration:   16
 * Started:     11/11/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

class Migration16 extends Migration14
{
    /**
     * Applications moving from `pre-new-admin` to `develop` will be on migration 14. This means that they
     * will not run the permission upgrade (migration 14). They WILL have the metadata columns
     * (develop: 15, pre-new-admin: 14).
     *
     * This migration ensures that the permission migrations happen again - this operation is safe to run twice.
     */
}
