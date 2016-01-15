<li class="file thumb" data-title="<?=$object->file->name->human?>" data-id="<?=$object->id?>">
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

            //  MP3
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
        <div class="actions">
            <a href="#" data-id="<?=$object->id?>" data-bucket="<?=$bucket->slug?>" data-file="<?=$object->file->name->disk?>" class="btn btn-xs btn-success insert">Insert</a>
            <?=anchor(site_url('cdn/manager/delete/' . $object->id . $_query_string, isPageSecure()), 'Delete', 'class="btn btn-xs btn-danger delete"')?>
            <?=anchor($url, $action, 'data-fancybox-title="' . $object->file->name->human . '" data-fancybox-type="' . $fancyboxType . '" class="' . $fancyboxClass . ' btn btn-xs btn-default"')?>
        </div>
    </div>
    <p class="filename">
        <?=$object->file->name->human?>
    </p>
</li>
