<?php

    echo '<tr class="file list" data-title="' . $object->filename_display . '" data-id="' . $object->id . '">';
        echo '<td class="filename">';

            if ($object->is_img) {

                //  Thumbnail
                echo img(array('src' => cdnCrop($object->id, 30, 30), 'class' => 'icon'));
                $fancyboxClass = 'cdn-fancybox';
                $fancyboxType  = '';
                $url           = cdnServe($object->id);
                $action        = 'View';


            } elseif ($object->mime == 'audio/mpeg') {

                //  Audio/Video
                echo '<div class="icon"><span class="fa fa-music" style="font-size:2.2em"></span></div>';
                $fancyboxClass = 'cdn-fancybox';
                $fancyboxType  = 'iframe';
                $url           = cdnServe($object->id);
                $action        = 'Play';


            } elseif ($object->mime == 'application/pdf') {

                //  PDF
                echo '<div class="icon"><span class="fa fa-file-o" style="font-size:2.2em"></span></div>';
                $fancyboxClass = 'cdn-fancybox';
                $fancyboxType  = 'iframe';
                $url           = cdnServe($object->id);
                $action        = 'View';

            } else {

                //  Generic file, force download
                echo '<div class="icon"><span class="fa fa-file-o" style="font-size:2.2em"></span></div>';
                $fancyboxClass = '';
                $fancyboxType  = '';
                $url           = cdnServe($object->id, true);
                $action        = 'Download';
            }

            echo $object->filename_display;

        echo '</td>';
        echo '<td class="mime">';
            echo $object->mime;
        echo '</td>';
        echo '<td class="filesize">';
            echo format_bytes($object->filesize);
        echo '</td>';
        echo '<td class="modified">';
            echo toUserDatetime($object->modified);
        echo '</td>';
        echo '<td class="actions">';

            echo '<a href="#" data-fieldid="' . $this->input->get('fieldid') . '" data-id="' . $object->id . '" data-bucket="' . $bucket->slug .'" data-file="' . $object->filename .'" class="awesome green small insert">Insert</a>';
            echo anchor(site_url('cdn/manager/delete/' . $object->id . '?' . $_SERVER['QUERY_STRING'], isPageSecure()), 'Delete', 'class="awesome red small delete"');
            echo anchor($url, $action, 'data-fancybox-title="' . $object->filename_display . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' awesome small"');

        echo '</td>';
    echo '</tr>';
