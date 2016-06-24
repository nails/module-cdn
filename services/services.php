<?php

return array(
    'properties' => array(

        /**
         * Define the default array of allowed types/extensions
         * This list should be restrictive enough so that malicious users can't do too much damage.
         */
        'bucketDefaultAllowedTypes' => array(
            //  Images
            'png', 'jpg', 'gif',
            //  Documents & Text
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'rtf', 'txt', 'csv', 'xml',
            //  Video
            'mp4', 'mov', 'm4v', 'mpg', 'mpeg', 'avi', 'ogv',
            //  Audio
            'mp3', 'wav', 'aiff', 'ogg', 'm4a', 'wma', 'aac', 'oga',
            //  Zips
            'zip'
        )
    ),
    'services' => array(
        'Cdn' => function () {
            if (class_exists('\App\Cdn\Library\Cdn')) {
                return new \App\Cdn\Library\Cdn();
            } else {
                return new \Nails\Cdn\Library\Cdn();
            }
        }
    )
);
