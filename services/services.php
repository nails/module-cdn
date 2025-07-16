<?php

use Nails\Cdn\Factory;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;
use Nails\Common;

return [
    'services'  => [
        'Cdn'           => function (
            ?Common\Service\Mime $oMimeService = null,
            ?Common\Service\Event $oEventService = null
        ): Service\Cdn {

            $oMimeService  = $oMimeService ?? \Nails\Factory::service('Mime');
            $oEventService = $oEventService ?? \Nails\Factory::service('Event');

            if (class_exists('\App\Cdn\Service\Cdn')) {
                return new \App\Cdn\Service\Cdn($oMimeService, $oEventService);
            } else {
                return new Service\Cdn($oMimeService, $oEventService);
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
        'ModelFieldObject'    => function (): Factory\Model\Field\CdnObject {
            if (class_exists('\App\Cdn\Factory\Model\Field\CdnObject')) {
                return new \App\Cdn\Factory\Model\Field\CdnObject();
            } else {
                return new Factory\Model\Field\CdnObject();
            }
        },
        'MonitorDetail'       => function (\Nails\Cdn\Interfaces\Monitor $oMonitor): Factory\Monitor\Detail {
            if (class_exists('\App\Cdn\Factory\Monitor\Detail')) {
                return new \App\Cdn\Factory\Monitor\Detail($oMonitor);
            } else {
                return new Factory\Monitor\Detail($oMonitor);
            }
        },
        'MonitorDetailAction' => function (): Factory\Monitor\Detail\Action {
            if (class_exists('\App\Cdn\Factory\Monitor\Detail\Action')) {
                return new \App\Cdn\Factory\Monitor\Detail\Action();
            } else {
                return new Factory\Monitor\Detail\Action();
            }
        },
    ],
    'resources' => [
        'Bucket'            => function ($resource, $model): Resource\Bucket {
            if (class_exists('\App\Cdn\Resource\Bucket')) {
                return new \App\Cdn\Resource\Bucket($resource, $model);
            } else {
                return new Resource\Bucket($resource, $model);
            }
        },
        'Object'            => function ($resource, $model): Resource\CdnObject {
            if (class_exists('\App\Cdn\Resource\CdnObject')) {
                return new \App\Cdn\Resource\CdnObject($resource, $model);
            } else {
                return new Resource\CdnObject($resource, $model);
            }
        },
        'ObjectFile'        => function ($resource, $model = null): Resource\CdnObject\File {
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\CdnObject\File')) {
                return new \App\Cdn\Resource\CdnObject\File($resource);
            } else {
                return new Resource\CdnObject\File($resource);
            }
        },
        'ObjectFileName'    => function ($resource, $model = null): Resource\CdnObject\File\Name {
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\CdnObject\File\Name')) {
                return new \App\Cdn\Resource\CdnObject\File\Name($resource);
            } else {
                return new Resource\CdnObject\File\Name($resource);
            }
        },
        'ObjectFileSize'    => function ($resource, $model = null): Resource\CdnObject\File\Size {
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\CdnObject\File\Size')) {
                return new \App\Cdn\Resource\CdnObject\File\Size($resource);
            } else {
                return new Resource\CdnObject\File\Size($resource);
            }
        },
        'ObjectImage'       => function ($resource, $model = null): Resource\CdnObject\Image {
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\CdnObject\Image')) {
                return new \App\Cdn\Resource\CdnObject\Image($resource);
            } else {
                return new Resource\CdnObject\Image($resource);
            }
        },
        'ObjectUrl'         => function ($resource, $model = null): Resource\CdnObject\Url {
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\CdnObject\Url')) {
                return new \App\Cdn\Resource\CdnObject\Url($resource);
            } else {
                return new Resource\CdnObject\Url($resource);
            }
        },
        'ObjectImport'      => function ($resource, $model): Resource\CdnObject\Import {
            if (class_exists('\App\Cdn\Resource\CdnObject\Import')) {
                return new \App\Cdn\Resource\CdnObject\Import($resource, $model);
            } else {
                return new Resource\CdnObject\Import($resource, $model);
            }
        },
        'ObjectTrash'       => function ($resource, $model): Resource\CdnObject {
            if (class_exists('\App\Cdn\Resource\CdnObject\Trash')) {
                return new \App\Cdn\Resource\CdnObject\Trash($resource, $model);
            } else {
                return new Resource\CdnObject\Trash($resource, $model);
            }
        },
        'Token'             => function ($resource, $model): Resource\Token {
            if (class_exists('\App\Cdn\Resource\Token')) {
                return new \App\Cdn\Resource\Token($resource, $model);
            } else {
                return new Resource\Token($resource, $model);
            }
        },
        'UrlGeneratorCrop'  => function (
            Service\Cdn $oCdn,
            Service\UrlGenerator $oService,
            int $iObjectId,
            int $iWidth,
            int $iHeight
        ): Resource\UrlGenerator\Crop {
            //  @todo (Pablo 2025-07-15) - this should be a factory
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
            //  @todo (Pablo 2025-07-15) - this should be a factory
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
            //  @todo (Pablo 2025-07-15) - this should be a factory
            if (class_exists('\App\Cdn\Resource\UrlGenerator\Serve')) {
                return new \App\Cdn\Resource\UrlGenerator\Serve($oCdn, $oService, $iObjectId, $bForceDownload);
            } else {
                return new Resource\UrlGenerator\Serve($oCdn, $oService, $iObjectId, $bForceDownload);
            }
        },
    ],
];
