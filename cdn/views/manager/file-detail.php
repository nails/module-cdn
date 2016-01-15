<li class="file detail" data-title="<?=$object->file->name->human?>" data-id="<?=$object->id?>">
    <div class="image">
        <?php

        if ($object->is_img) {

            //  Thumbnail
            echo img(cdnScale($object->id, 150, 175));
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = '';
            $url           = cdnServe($object->id);
            $action        = 'View';


        } elseif ($object->file->mime == 'audio/mpeg') {

            //  Audio/Video
            echo '<span class="fa fa-music" style="font-size:5em"></span>';
            $fancyboxClass = 'cdn-fancybox';
            $fancyboxType  = 'iframe';
            $url           = cdnServe($object->id);
            $action        = 'Play';

        } elseif ($object->file->mime == 'application/pdf') {

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

        ?>
    </div>
    <div class="details">
        <span class="filename">
            <?=$object->file->name->human?>
        </span>
        <div class="type">
            <strong>Type:</strong>
            <?=$object->file->mime?>
        </div>
        <div class="filesize">
            <strong>Filesize:</strong>
            <?=$object->file->size->human?>
        </div>
        <div class="created">
            <strong>Created:</strong>
            <?=toUserDatetime($object->created)?>
        </div>
        <div class="modified">
            <strong>Modified:</strong>
            <?=toUserDatetime($object->modified)?>
        </div>
        <div class="actions">
            <a href="#" data-id="<?=$object->id?>" data-bucket="<?=$bucket->slug?>" data-file="<?=$object->file->name->disk?>" class="btn btn-xs btn-success insert">Insert</a>
            <?=anchor(site_url('cdn/manager/delete/' . $object->id . '?' . $_SERVER['QUERY_STRING'], isPageSecure()), 'Delete', 'class="btn btn-xs btn-danger delete"')?>
            <?=anchor($url, $action, 'data-fancybox-title="' . $object->file->name->human . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' btn btn-xs btn-default"')?>
        </div>
    </div>
    <div class="clear"></div>
</li>
