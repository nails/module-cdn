<?php

namespace Nails\Cdn\Resource\CdnObject;

use Nails\Cdn\Constants;
use Nails\Cdn\Resource\CdnObject\File\Name;
use Nails\Cdn\Resource\CdnObject\File\Size;
use Nails\Common\Resource;
use Nails\Factory;

/**
 * Class File
 *
 * @package Nails\Cdn\Resource\CdnObject
 */
class File extends Resource
{
    /**
     * @var Name
     */
    public $name;

    /**
     * @var string
     */
    public $mime;

    /**
     * @var string
     */
    public $ext;

    /**
     * @var Size
     */
    public $size;

    // --------------------------------------------------------------------------

    /**
     * File constructor.
     *
     * @param Resource|\stdClass|array $oObj The data to format
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct($oObj)
    {
        parent::__construct($oObj);
        $this->name = Factory::resource(
            'ObjectFileName',
            Constants::MODULE_SLUG,
            $oObj->name
        );
        $this->size = Factory::resource(
            'ObjectFileSize',
            Constants::MODULE_SLUG,
            $oObj->size
        );
    }
}
