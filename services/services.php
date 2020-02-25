<?php

use Nails\Common;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;
use Nails\Cdn\Model;
use Nails\Factory;

return [
    'properties' => [

        /**
         * Define the default array of allowed types/extensions
         * This list should be restrictive enough so that malicious users can't do too much damage.
         */
        'bucketDefaultAllowedTypes'         => [
            //  Images
            'png',
            'jpg',
            'gif',
            //  Documents & Text
            'pdf',
            'doc',
            'docx',
            'ppt',
            'pptx',
            'xls',
            'xlsx',
            'rtf',
            'txt',
            'csv',
            'xml',
            'json',
            'js',
            'css',
            //  Video
            'mp4',
            'mov',
            'm4v',
            'mpg',
            'mpeg',
            'avi',
            'ogv',
            //  Audio
            'mp3',
            'wav',
            'aiff',
            'ogg',
            'm4a',
            'wma',
            'aac',
            'oga',
            //  Zips
            'zip',
        ],

        /**
         * Allow images to be manipulated to any size via the URL. Not recommended.
         * https://github.com/nails/module-cdn/blob/develop/docs/transformation/security.md
         */
        'allowDangerousImageTransformation' => false,

        /**
         * For how many days to keep an item in the trash
         *
         * @var int
         */
        'trashRetention'                    => 180,
    ],
    'services'   => [
        'Cdn'           => function (Common\Service\Mime $oMimeService = null): Service\Cdn {

            $oMimeService = $oMimeService ?? Factory::service('Mime');

            if (class_exists('\App\Cdn\Service\Cdn')) {
                return new \App\Cdn\Service\Cdn($oMimeService);
            } else {
                return new Service\Cdn($oMimeService);
            }
        },
        'StorageDriver' => function (): Service\StorageDriver {
            if (class_exists('\App\Cdn\Service\StorageDriver')) {
                return new \App\Cdn\Service\StorageDriver();
            } else {
                return new Service\StorageDriver();
            }
        },
        'UrlGenerator'  => function (): Service\UrlGenerator {
            if (class_exists('\App\Cdn\Service\UrlGenerator')) {
                return new \App\Cdn\Service\UrlGenerator();
            } else {
                return new Service\UrlGenerator();
            }
        },
    ],
    'models'     => [
        'Bucket'      => function () {
            if (class_exists('\App\Cdn\Model\Bucket')) {
                return new \App\Cdn\Model\Bucket();
            } else {
                return new Model\Bucket();
            }
        },
        'Object'      => function () {
            if (class_exists('\App\Cdn\Model\CdnObject')) {
                return new \App\Cdn\Model\CdnObject();
            } else {
                return new Model\CdnObject();
            }
        },
        'ObjectTrash' => function () {
            if (class_exists('\App\Cdn\Model\CdnObject\Trash')) {
                return new \App\Cdn\Model\CdnObject\Trash();
            } else {
                return new Model\CdnObject\Trash();
            }
        },
        'Token'       => function () {
            if (class_exists('\App\Cdn\Model\Token')) {
                return new \App\Cdn\Model\Token();
            } else {
                return new Model\Token();
            }
        },
    ],
    'resources'  => [
        'Bucket'            => function ($oObj): Resource\Bucket {
            if (class_exists('\App\Cdn\Resource\Bucket')) {
                return new \App\Cdn\Resource\Bucket($oObj);
            } else {
                return new Resource\Bucket($oObj);
            }
        },
        'Object'            => function ($oObj): Resource\CdnObject {
            if (class_exists('\App\Cdn\Resource\CdnObject')) {
                return new \App\Cdn\Resource\CdnObject($oObj);
            } else {
                return new Resource\CdnObject($oObj);
            }
        },
        'ObjectFile'        => function ($oObj): Resource\CdnObject\File {
            if (class_exists('\App\Cdn\Resource\CdnObject\File')) {
                return new \App\Cdn\Resource\CdnObject\File($oObj);
            } else {
                return new Resource\CdnObject\File($oObj);
            }
        },
        'ObjectFileName'    => function ($oObj): Resource\CdnObject\File\Name {
            if (class_exists('\App\Cdn\Resource\CdnObject\File\Name')) {
                return new \App\Cdn\Resource\CdnObject\File\Name($oObj);
            } else {
                return new Resource\CdnObject\File\Name($oObj);
            }
        },
        'ObjectFileSize'    => function ($oObj): Resource\CdnObject\File\Size {
            if (class_exists('\App\Cdn\Resource\CdnObject\File\Size')) {
                return new \App\Cdn\Resource\CdnObject\File\Size($oObj);
            } else {
                return new Resource\CdnObject\File\Size($oObj);
            }
        },
        'ObjectImage'       => function ($oObj): Resource\CdnObject\Image {
            if (class_exists('\App\Cdn\Resource\CdnObject\Image')) {
                return new \App\Cdn\Resource\CdnObject\Image($oObj);
            } else {
                return new Resource\CdnObject\Image($oObj);
            }
        },
        'ObjectUrl'         => function ($oObj): Resource\CdnObject\Url {
            if (class_exists('\App\Cdn\Resource\CdnObject\Url')) {
                return new \App\Cdn\Resource\CdnObject\Url($oObj);
            } else {
                return new Resource\CdnObject\Url($oObj);
            }
        },
        'Token'             => function ($oObj): Resource\Token {
            if (class_exists('\App\Cdn\Resource\Token')) {
                return new \App\Cdn\Resource\Token($oObj);
            } else {
                return new Resource\Token($oObj);
            }
        },
        'UrlGeneratorCrop'  => function (
            Service\UrlGenerator $oSerice,
            int $iObjectId,
            int $iWidth,
            int $iHeight
        ): Resource\UrlGenerator\Crop {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Crop')) {
                return new \App\Cdn\Resource\UrlGenerator\Crop($oSerice, $iObjectId, $iWidth, $iHeight);
            } else {
                return new Resource\UrlGenerator\Crop($oSerice, $iObjectId, $iWidth, $iHeight);
            }
        },
        'UrlGeneratorScale' => function (
            Service\UrlGenerator $oSerice,
            int $iObjectId,
            int $iWidth,
            int $iHeight
        ): Resource\UrlGenerator\Scale {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Scale')) {
                return new \App\Cdn\Resource\UrlGenerator\Scale($oSerice, $iObjectId, $iWidth, $iHeight);
            } else {
                return new Resource\UrlGenerator\Scale($oSerice, $iObjectId, $iWidth, $iHeight);
            }
        },
        'UrlGeneratorServe' => function (
            Service\UrlGenerator $oSerice,
            int $iObjectId
        ): Resource\UrlGenerator\Serve {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Serve')) {
                return new \App\Cdn\Resource\UrlGenerator\Serve($oSerice, $iObjectId);
            } else {
                return new Resource\UrlGenerator\Serve($oSerice, $iObjectId);
            }
        },
    ],
];
