<?php

    echo '<li class="file thumb" data-title="' . $object->filename_display . '" data-id="' . $object->id . '">';
    echo '<div class="image">';

        if ($object->is_img) {

            //  Thumbnail
            echo img(cdn_scale($object->id, 150, 175));
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = '';
            $url           = cdn_serve($object->id);
            $action        = 'View';


        } elseif ($object->mime == 'audio/mpeg') {

            //  MP3
            echo '<span class="fa fa-music" style="font-size:5em"></span>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdn_serve($object->id);
            $action        = 'Play';

        } elseif ($object->mime == 'application/pdf') {

            //  PDF
            echo '<span class="fa fa-file-o" style="font-size:5em"></span>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdn_serve($object->id);
            $action        = 'View';

        } else {

            //  Generic file, force download
            echo '<span class="fa fa-file-o" style="font-size:5em"></span>';
            $fancyboxClass = '';
            $fancyboxType  = '';
            $url           = cdn_serve($object->id, true);
            $action        = 'Download';
        }

        //  Actions
        echo '<div class="actions">';

            echo '<a href="#" data-id="' . $object->id . '" data-bucket="' . $bucket->slug .'" data-file="' . $object->filename .'" class="awesome green small insert">Insert</a>';
            echo anchor(site_url('cdn/manager/delete/' . $object->id . $_query_string, isPageSecure()), 'Delete', 'class="awesome red small delete"');
            echo anchor($url, $action, 'data-fancybox-title="' . $object->filename_display . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' awesome small"');

        echo '</div>';

    echo '</div>';

    //  Filename
    echo '<p class="filename">' . $object->filename_display . '</p>';
    echo '</li>';
