<div class="group-cdn object browse trash">
    <p>
        The following items are currently in the CDN trash.
    </p>
    <?php

        echo \Nails\Admin\Helper::loadSearch($search);
        echo \Nails\Admin\Helper::loadPagination($pagination);

    ?>
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
                    <th class="trashed datetime">Trashed</th>
                    <th class="user">Trashed By</th>
                    <th class="filesize">Filesize</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php

                if ($objects) {

                    foreach ($objects as $object) {

                        echo '<tr>';
                            echo '<td class="id">' . number_format($object->id) . '</td>';
                            echo '<td class="thumbnail">';

                                switch ($object->mime) {

                                    case 'image/png' :
                                    case 'image/jpeg' :
                                    case 'image/gif' :

                                        echo anchor(cdn_serve($object->id), img(cdn_scale($object->id, 64, 64)), 'class="fancybox"');
                                        break;

                                    case 'audio/mpeg' :

                                        echo '<span class="fa fa-music"></span>';
                                        break;

                                    default :

                                        echo '<span class="fa fa-file-o"></span>';
                                        break;

                                }

                            echo '</td>';
                            echo '<td class="bucket">' . $object->bucket->label . '</td>';
                            echo '<td class="mime">' . $object->mime . '</td>';
                            echo '<td class="filename">' . $object->filename_display . '</td>';
                            echo \Nails\Admin\Helper::loadUserCell($object->creator);
                            echo \Nails\Admin\Helper::loadDatetimeCell($object->created);
                            echo \Nails\Admin\Helper::loadDatetimeCell($object->modified);
                            echo \Nails\Admin\Helper::loadDatetimeCell($object->trashed);
                            echo \Nails\Admin\Helper::loadUserCell($object->trasher);
                            echo '<td class="filesize">' . format_bytes($object->filesize) . '</td>';
                            echo '<td class="actions">';

                                if ($object->is_img) {

                                    echo anchor(cdn_serve($object->id), 'View', 'class="awesome small fancybox"');

                                } else {

                                    echo anchor(cdn_serve($object->id), 'View', 'class="awesome small fancybox" data-fancybox-type="iframe"');
                                }

                                if (userHasPermission('admin.cdnadmin:0.can_restore_trash')) {

                                    echo anchor('admin/cdn/trash//restore/' . $object->id . $return, 'Restore', 'class="awesome small green"');
                                }

                                if (userHasPermission('admin.cdnadmin:0.can_purge_trash')) {

                                    $ids = 'ids=' . $object->id;
                                    $ids = empty($return) ? '?' . $ids : '&' . $ids;

                                    $title = 'Are you sure?';
                                    $body  = 'You will <strong>permenantly</strong> delete this object. This action cannot be undone.';

                                    echo anchor('admin/cdn/trash//purge' . $return . $ids, 'Delete', 'data-title="' . $title . '" data-body="' . $body . '" class="confirm awesome small red"');
                                }

                            echo '</td>';
                        echo '</tr>';
                    }

                } else {

                    echo '<tr>';
                        echo '<td colspan="12" class="no-data">';
                            echo 'No Objects Found';
                        echo '</td>';
                    echo '</tr>';

                }

            ?>
            </tbody>
        </table>
    </div>
    <?php

        echo \Nails\Admin\Helper::loadPagination($pagination);

    ?>
</div>