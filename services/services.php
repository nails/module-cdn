<?php

use Nails\Common\Service;
use Nails\Factory;

return [
    'properties' => [

        /**
         * Define the default array of allowed types/extensions
         * This list should be restrictive enough so that malicious users can't do too much damage.
         */
        'bucketDefaultAllowedTypes' => [
            //  Images
            'png', 'jpg', 'gif',
            //  Documents & Text
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'rtf', 'txt', 'csv', 'xml', 'json',
            //  Video
            'mp4', 'mov', 'm4v', 'mpg', 'mpeg', 'avi', 'ogv',
            //  Audio
            'mp3', 'wav', 'aiff', 'ogg', 'm4a', 'wma', 'aac', 'oga',
            //  Zips
            'zip',
        ],

        /**
         * Allow images to be manipulated to any size via the URL. Not recommended.
         * https://github.com/nails/module-cdn/blob/develop/docs/transformation/security.md
         */
        'allowDangerousImageTransformation' => false,
    ],
    'services'   => [
        'Cdn'           => function (Service\Mime $oMimeService = null) {

            if (!$oMimeService) {
                $oMimeService = Factory::service('Mime');
            }

            if (class_exists('\App\Cdn\Service\Cdn')) {
                return new \App\Cdn\Service\Cdn($oMimeService);
            } else {
                return new \Nails\Cdn\Service\Cdn($oMimeService);
            }
        },
        'StorageDriver' => function () {
            if (class_exists('\App\Cdn\Service\StorageDriver')) {
                return new \App\Cdn\Service\StorageDriver();
            } else {
                return new \Nails\Cdn\Service\StorageDriver();
            }
        },
    ],
    'models'     => [
        'Bucket'      => function () {
            if (class_exists('\App\Cdn\Model\Bucket')) {
                return new \App\Cdn\Model\Bucket();
            } else {
                return new \Nails\Cdn\Model\Bucket();
            }
        },
        'Object'      => function () {
            if (class_exists('\App\Cdn\Model\CdnObject')) {
                return new \App\Cdn\Model\CdnObject();
            } else {
                return new \Nails\Cdn\Model\CdnObject();
            }
        },
        'ObjectTrash' => function () {
            if (class_exists('\App\Cdn\Model\CdnObject\Trash')) {
                return new \App\Cdn\Model\CdnObject\Trash();
            } else {
                return new \Nails\Cdn\Model\CdnObject\Trash();
            }
        },
    ],
];
