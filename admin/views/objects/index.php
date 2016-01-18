<div class="group-cdn object browse">
    <p>
        Browse all items stored in the site's CDN.
    </p>
    <?=adminHelper('loadSearch', $search)?>
    <?=adminHelper('loadPagination', $pagination)?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="id">ID</th>
                    <th class="thumbnail"></th>
                    <th class="bucket">Bucket</th>
                    <th class="mime">Type</th>
                    <th class="filename">Filename</th>
                    <th class="user">Uploader</th>
                    <th class="created datetime">Created</th>
                    <th class="modified datetime">Modified</th>
                    <th class="filesize">Filesize</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php

            if ($objects) {

                foreach ($objects as $object) {

                    ?>
                    <tr>
                        <td class="id">
                            <?=number_format($object->id)?>
                        </td>
                        <td class="thumbnail">
                            <?php

                            switch ($object->file->mime) {

                                case 'image/png' :
                                case 'image/jpeg' :
                                case 'image/gif' :

                                    echo anchor(
                                        cdnServe($object->id),
                                        img(cdnScale($object->id, 64, 64)),
                                        'class="fancybox"'
                                    );
                                    break;

                                case 'audio/mpeg' :

                                    echo '<span class="fa fa-music"></span>';
                                    break;

                                default :

                                    echo '<span class="fa fa-file-o"></span>';
                                    break;
                            }

                            ?>
                        </td>
                        <td class="bucket">
                            <?=$object->bucket->label?>
                        </td>
                        <td class="mime">
                            .<?=$object->file->ext?>
                            <small title="<?=$object->file->mime?>">
                                <?=$object->file->mime?>
                            </small>
                        </td>
                        <td class="filename">
                            <?=$object->file->name->human?>
                        </td>
                        <?=adminHelper('loadUserCell', $object->creator)?>
                        <?=adminHelper('loadDatetimeCell', $object->created)?>
                        <?=adminHelper('loadDatetimeCell', $object->modified)?>
                        <td class="filesize">
                            <?=$object->file->size->human?>
                        </td>
                        <td class="actions">
                            <?php

                            if (userHasPermission('admin:cdn:objects:edit')) {

                                echo anchor(
                                    'admin/cdn/objects/edit/' . $object->id . $return,
                                    'Edit',
                                    'class="btn btn-xs btn-primary"'
                                );
                            }

                            if (userHasPermission('admin:cdn:objects:delete')) {

                                echo anchor(
                                    'admin/cdn/objects/delete/' . $object->id . $return,
                                    'Delete',
                                    'data-body="Deleting an item will attempt to disconnect it from resources which depend on it. The object will be recoverable but dependencies won\'t." class="confirm btn btn-xs btn-danger"'
                                );
                            }

                            if ($object->is_img) {

                                echo anchor(
                                    cdnServe($object->id),
                                    'View',
                                    'class="btn btn-xs btn-success fancybox"'
                                );

                            } else {

                                echo anchor(
                                    cdnServe($object->id),
                                    'View',
                                    'class="btn btn-xs btn-success fancybox" data-fancybox-type="iframe"'
                                );
                            }

                            ?>
                        </td>
                    </tr>
                    <?php
                }

            } else {

                ?>
                <tr>
                    <td colspan="10" class="no-data">
                        No Objects Found
                    </td>
                </tr>
                <?php
            }

            ?>
            </tbody>
        </table>
    </div>
    <?=adminHelper('loadPagination', $pagination)?>
</div>