<?php

namespace Nails\Cdn\Resource;

use Nails\Cdn\Resource\CdnObject\File;
use Nails\Cdn\Resource\CdnObject\Image;
use Nails\Cdn\Resource\CdnObject\Url;
use Nails\Common\Resource\Entity;
use Nails\Factory;

/**
 * Class CdnObject
 *
 * @package Nails\Cdn\Resource
 */
class CdnObject extends Entity
{
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

    /**
     * @var Url
     */
    public $url;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $filename_display;

    /**
     * @var string
     */
    protected $mime;

    /**
     * @var string
     */
    protected $filesize;

    /**
     * @var int
     */
    protected $img_width;

    /**
     * @var int
     */
    protected $img_height;

    /**
     * @var string
     */
    protected $img_orientation;

    /**
     * @var bool
     */
    protected $is_animated;

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
                    'disk'  => $this->filename,
                    'human' => $this->filename_display,
                ],
                'mime' => $this->mime,
                'ext'  => strtolower(pathinfo($this->filename, PATHINFO_EXTENSION)),
                'size' => (object) [
                    'bytes' => $this->filesize,
                ],
            ]
        );

        // --------------------------------------------------------------------------

        $this->is_img = (bool) preg_match('/^image\/.+/', $this->mime);
        if ($this->is_img) {
            $this->img = Factory::resource(
                'ObjectImage',
                'nails/module-cdn',
                [
                    'width'       => $this->img_width,
                    'height'      => $this->img_height,
                    'orientation' => $this->img_orientation,
                    'animated'    => $this->is_animated,
                ]
            );
        } else {
            unset($this->img);
        }

        // --------------------------------------------------------------------------

        if (empty($this->bucket)) {
            unset($this->bucket);
        } else {
            unset($this->bucket_id);
        }

        // --------------------------------------------------------------------------

        $this->url = Factory::resource(
            'ObjectUrl',
            'nails/module-cdn',
            (object) [
                'id'     => $this->id,
                'is_img' => $this->is_img,
            ]
        );

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
