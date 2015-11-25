
<tr class="file list" data-title="<?=$object->filename_display?>" data-id="<?=$object->id?>">
    <td class="filename">
        <?php

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

        ?>
    </td>
    <td class="mime">
        <?=$object->mime?>
    </td>
    <td class="filesize">
        <?=format_bytes($object->filesize)?>
    </td>
    <td class="modified">
        <?=toUserDatetime($object->modified)?>
    </td>
    <td class="actions">
        <a href="#" data-fieldid="' . $this->input->get('fieldid') . '" data-id="' . $object->id . '" data-bucket="' . $bucket->slug .'" data-file="' . $object->filename .'" class="btn btn-xs btn-success insert">Insert</a>
        <?=anchor(site_url('cdn/manager/delete/' . $object->id . '?' . $_SERVER['QUERY_STRING'], isPageSecure()), 'Delete', 'class="btn btn-xs btn-danger delete"')?>
        <?=anchor($url, $action, 'data-fancybox-title="' . $object->filename_display . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' btn btn-xs btn-default"')?>
    </td>
</tr>
