<?php

namespace Nails\Cdn\Resource;

use Nails\Cdn\Resource\CdnObject\File;
use Nails\Cdn\Resource\CdnObject\Image;
use Nails\Common\Resource;
use Nails\Factory;

/**
 * Class CdnObject
 *
 * @package Nails\Cdn\Resource
 */
class CdnObject extends Resource
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $bucket_id;

    /**
     * @var Bucket
     */
    public $bucket;

    /**
     * @var string
     */
    public $md5_hash;

    /**
     * @var int
     */
    public $serves;

    /**
     * @var int
     */
    public $downloads;

    /**
     * @var int
     */
    public $thumbs;

    /**
     * @var int
     */
    public $scales;

    /**
     * @var string
     */
    public $driver;

    /**
     * @var string
     */
    public $created;

    /**
     * @var int
     */
    public $created_by;

    /**
     * @var string
     */
    public $modified;

    /**
     * @var int
     */
    public $modified_by;

    /**
     * @var File
     */
    public $file;

    /**
     * @var bool
     */
    public $is_img;

    /**
     * @var Image
     */
    public $img;

    // --------------------------------------------------------------------------

    /**
     * CdnObject constructor.
     *
     * @param Resource|\stdClass|array $oObj The data to format
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct($oObj)
    {
        parent::__construct($oObj);

        // --------------------------------------------------------------------------

        $this->file = Factory::resource(
            'ObjectFile',
            'nails/module-cdn',
            (object) [
                'name' => (object) [
                    'disk'  => $oObj->filename,
                    'human' => $oObj->filename_display,
                ],
                'mime' => $oObj->mime,
                'ext'  => strtolower(pathinfo($oObj->filename, PATHINFO_EXTENSION)),
                'size' => (object) [
                    'bytes' => $oObj->filesize,
                ],
            ]
        );

        // --------------------------------------------------------------------------

        $this->is_img = (bool) preg_match('/^image\/.+/', $oObj->mime);
        if ($this->is_img) {
            $this->img = Factory::resource(
                'ObjectImage',
                'nails/module-cdn',
                [
                    'width'       => $oObj->img_width,
                    'height'      => $oObj->img_height,
                    'orientation' => $oObj->img_orientation,
                    'animated'    => $oObj->is_animated,
                ]
            );
        } else {
            unset($this->img);
        }

        // --------------------------------------------------------------------------

        if (empty($oObj->bucket)) {
            unset($this->bucket);
        } else {
            unset($this->bucket_id);
        }

        // --------------------------------------------------------------------------

        unset($this->filename);
        unset($this->filename_display);
        unset($this->mime);
        unset($this->filesize);
        unset($this->img_width);
        unset($this->img_height);
        unset($this->img_orientation);
        unset($this->is_animated);
    }
}
