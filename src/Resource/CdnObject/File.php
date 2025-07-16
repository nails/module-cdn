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
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct(self|\stdClass|array $resource)
    {
        parent::__construct($resource);
        $this->name = Factory::resource(
            'ObjectFileName',
            Constants::MODULE_SLUG,
            $resource->name
        );
        $this->size = Factory::resource(
            'ObjectFileSize',
            Constants::MODULE_SLUG,
            $resource->size
        );
    }
}
