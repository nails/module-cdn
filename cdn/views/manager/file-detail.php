<?php

    echo '<li class="file detail" data-title="' . $object->filename_display . '" data-id="' . $object->id . '">';

    echo '<div class="image">';

        if ($object->is_img) {

            //  Thumbnail
            echo img(cdnScale($object->id, 150, 175));
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = '';
            $url           = cdnServe($object->id);
            $action        = 'View';


        } elseif ($object->mime == 'audio/mpeg') {

            //  Audio/Video
            echo '<span class="fa fa-music" style="font-size:5em"></span>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdnServe($object->id);
            $action        = 'Play';

        } elseif ($object->mime == 'application/pdf') {

            //  PDF
            echo '<span class="fa fa-file-o" style="font-size:5em"></span>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdnServe($object->id);
            $action        = 'View';

        } else {

            //  Generic file, force download
            echo '<span class="fa fa-file-o" style="font-size:5em"></span>';
            $fancyboxClass = '';
            $fancyboxType  = '';
            $url           = cdnServe($object->id, true);
            $action        = 'Download';
        }

    echo '</div>';

    //  Filename
    echo '<div class="details">';
        echo '<span class="filename">';
            echo $object->filename_display;
        echo '</span>';
        echo '<div class="type">';
            echo '<strong>Type:</strong> ';
            echo $object->mime;
        echo '</div>';
        echo '<div class="filesize">';
            echo '<strong>Filesize:</strong> ';
            echo format_bytes($object->filesize);
        echo '</div>';
        echo '<div class="created">';
            echo '<strong>Created:</strong> ';
            echo toUserDatetime($object->created);
        echo '</div>';
        echo '<div class="modified">';
            echo '<strong>Modified:</strong> ';
            echo toUserDatetime($object->modified);
        echo '</div>';
        echo '<div class="actions">';

            echo '<a href="#" data-id="' . $object->id . '" data-bucket="' . $bucket->slug .'" data-file="' . $object->filename .'" class="awesome green small insert">Insert</a>';
            echo anchor(site_url('cdn/manager/delete/' . $object->id . '?' . $_SERVER['QUERY_STRING'], isPageSecure()), 'Delete', 'class="awesome red small delete"');
            echo anchor($url, $action, 'data-fancybox-title="' . $object->filename_display . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' awesome small"');

        echo '</div>';
    echo '</div>';
    echo '<div class="clear"></div>';
    echo '</li>';
