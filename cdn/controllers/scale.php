<?php

/**
 * This class handles the "scale" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

require_once 'crop.php';

class Scale extends Crop
{
    /**
     * Generate a thumbnail
     */
    public function index()
    {
        return parent::index('SCALE');
    }
}
