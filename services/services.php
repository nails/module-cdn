<?php

use Nails\Common;
use Nails\Cdn\Resource;
use Nails\Cdn\Factory;
use Nails\Cdn\Service;
use Nails\Cdn\Model;

return [
    'services'  => [
        'Cdn'           => function (Common\Service\Mime $oMimeService = null): Service\Cdn {

            $oMimeService = $oMimeService ?? \Nails\Factory::service('Mime');

            if (class_exists('\App\Cdn\Service\Cdn')) {
                return new \App\Cdn\Service\Cdn($oMimeService);
            } else {
                return new Service\Cdn($oMimeService);
            }
        },
        'Monitor'       => function (): Service\Monitor {
            if (class_exists('\App\Cdn\Service\Monitor')) {
                return new \App\Cdn\Service\Monitor();
            } else {
                return new Service\Monitor();
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
    'models'    => [
        'Bucket'       => function (): Model\Bucket {
            if (class_exists('\App\Cdn\Model\Bucket')) {
                return new \App\Cdn\Model\Bucket();
            } else {
                return new Model\Bucket();
            }
        },
        'Object'       => function (): Model\CdnObject {
            if (class_exists('\App\Cdn\Model\CdnObject')) {
                return new \App\Cdn\Model\CdnObject();
            } else {
                return new Model\CdnObject();
            }
        },
        'ObjectImport' => function (): Model\CdnObject\Import {
            if (class_exists('\App\Cdn\Model\CdnObject\Import')) {
                return new \App\Cdn\Model\CdnObject\Import();
            } else {
                return new Model\CdnObject\Import();
            }
        },
        'ObjectTrash'  => function (): Model\CdnObject\Trash {
            if (class_exists('\App\Cdn\Model\CdnObject\Trash')) {
                return new \App\Cdn\Model\CdnObject\Trash();
            } else {
                return new Model\CdnObject\Trash();
            }
        },
        'Token'        => function (): Model\Token {
            if (class_exists('\App\Cdn\Model\Token')) {
                return new \App\Cdn\Model\Token();
            } else {
                return new Model\Token();
            }
        },
    ],
    'factories' => [
        'MonitorDetail' => function (\Nails\Cdn\Interfaces\Monitor $oMonitor): Factory\Monitor\Detail {
            if (class_exists('\App\Cdn\Factory\Monitor\Detail')) {
                return new \App\Cdn\Factory\Monitor\Detail($oMonitor);
            } else {
                return new Factory\Monitor\Detail($oMonitor);
            }
        },
    ],
    'resources' => [
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
        'ObjectImport'      => function ($oObj): Resource\CdnObject\Import {
            if (class_exists('\App\Cdn\Resource\CdnObject\Import')) {
                return new \App\Cdn\Resource\CdnObject\Import($oObj);
            } else {
                return new Resource\CdnObject\Import($oObj);
            }
        },
        'ObjectTrash'       => function ($oObj): Resource\CdnObject {
            if (class_exists('\App\Cdn\Resource\CdnObject\Trash')) {
                return new \App\Cdn\Resource\CdnObject\Trash($oObj);
            } else {
                return new Resource\CdnObject\Trash($oObj);
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
            Service\Cdn $oCdn,
            Service\UrlGenerator $oService,
            int $iObjectId,
            int $iWidth,
            int $iHeight
        ): Resource\UrlGenerator\Crop {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Crop')) {
                return new \App\Cdn\Resource\UrlGenerator\Crop($oCdn, $oService, $iObjectId, $iWidth, $iHeight);
            } else {
                return new Resource\UrlGenerator\Crop($oCdn, $oService, $iObjectId, $iWidth, $iHeight);
            }
        },
        'UrlGeneratorScale' => function (
            Service\Cdn $oCdn,
            Service\UrlGenerator $oService,
            int $iObjectId,
            int $iWidth,
            int $iHeight
        ): Resource\UrlGenerator\Scale {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Scale')) {
                return new \App\Cdn\Resource\UrlGenerator\Scale($oCdn, $oService, $iObjectId, $iWidth, $iHeight);
            } else {
                return new Resource\UrlGenerator\Scale($oCdn, $oService, $iObjectId, $iWidth, $iHeight);
            }
        },
        'UrlGeneratorServe' => function (
            Service\Cdn $oCdn,
            Service\UrlGenerator $oService,
            int $iObjectId,
            bool $bForceDownload
        ): Resource\UrlGenerator\Serve {
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Serve')) {
                return new \App\Cdn\Resource\UrlGenerator\Serve($oCdn, $oService, $iObjectId, $bForceDownload);
            } else {
                return new Resource\UrlGenerator\Serve($oCdn, $oService, $iObjectId, $bForceDownload);
            }
        },
    ],
];
