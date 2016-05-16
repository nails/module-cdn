
<tr class="file list" data-title="<?=$object->file->name->human?>" data-id="<?=$object->id?>">
    <td class="filename">
        <?php

        if ($object->is_img) {

            //  Thumbnail
            echo img(array('src' => cdnCrop($object->id, 30, 30), 'class' => 'icon'));
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = '';
            $url           = cdnServe($object->id);
            $action        = 'View';


        } elseif ($object->file->mime == 'audio/mpeg') {

            //  Audio/Video
            echo '<div class="icon"><span class="fa fa-music" style="font-size:2.2em"></span></div>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdnServe($object->id);
            $action        = 'Play';


        } elseif ($object->file->mime == 'application/pdf') {

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

        echo $object->file->name->human;

        ?>
    </td>
    <td class="mime">
        <?=$object->file->mime?>
    </td>
    <td class="filesize">
        <?=$object->file->size->human?>
    </td>
    <td class="modified">
        <?=toUserDatetime($object->modified)?>
    </td>
    <td class="actions">
        <a href="#" data-id="<?=$object->id?>" data-bucket="<?=$bucket->slug?>" data-file="<?=$object->file->name->disk?>" class="btn btn-xs btn-success insert">Insert</a>
        <?=anchor(site_url('cdn/manager/delete/' . $object->id . '?' . $_SERVER['QUERY_STRING'], isPageSecure()), 'Delete', 'class="btn btn-xs btn-danger delete"')?>
        <?=anchor($url, $action, 'data-fancybox-title="' . $object->file->name->human . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' btn btn-xs btn-default"')?>
    </td>
</tr>
