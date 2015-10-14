<?php

/**
 * This class handles the "thumb" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

require_once 'crop.php';

class Thumb extends Crop
{
    /**
     * Generate a thumbnail
     * @return  void
     **/
    public function index()
    {
        return parent::index('CROP');
    }
}
