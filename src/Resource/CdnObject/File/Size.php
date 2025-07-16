<?php

namespace Nails\Cdn\Resource\CdnObject\File;

use Nails\Cdn\Constants;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Helper\File;
use Nails\Common\Resource;
use Nails\Factory;

/**
 * Class Size
 *
 * @package Nails\Cdn\Resource\CdnObject\File
 */
class Size extends Resource
{
    /**
     * @var int
     */
    public $bytes;

    /**
     * @var int
     */
    public $kilobytes;

    /**
     * @var int
     */
    public $megabytes;

    /**
     * @var int
     */
    public $gigabytes;

    /**
     * @var string
     */
    public $human;

    // --------------------------------------------------------------------------

    /**
     * Size constructor.
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct(self|\stdClass|array $resource)
    {
        parent::__construct($resource);

        /** @var Cdn $oCdnService */
        $oCdnService = Factory::service('Cdn', Constants::MODULE_SLUG);

        $this->kilobytes = round($this->bytes / File::BYTE_MULTIPLIER_KB, $oCdnService::FILE_SIZE_PRECISION);
        $this->megabytes = round($this->bytes / File::BYTE_MULTIPLIER_MB, $oCdnService::FILE_SIZE_PRECISION);
        $this->gigabytes = round($this->bytes / File::BYTE_MULTIPLIER_GB, $oCdnService::FILE_SIZE_PRECISION);
        $this->human     = File::formatBytes($this->bytes);
    }
}
