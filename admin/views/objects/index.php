<div class="group-cdn object browse">
    <p>
        Browse all items stored in the site's CDN.
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
                            echo '<td class="filesize">' . format_bytes($object->filesize) . '</td>';
                            echo '<td class="actions">';

                                if (userHasPermission('admin.cdnadmin:0.can_edit_objects')) {

                                    echo anchor('admin/cdn/objects/edit/' . $object->id . $return, 'Edit', 'class="awesome small"');
                                }

                                if (userHasPermission('admin.cdnadmin:0.can_delete_objects')) {

                                    echo anchor('admin/cdn/objects/delete/' . $object->id . $return, 'Delete', 'data-title="Are you sure?" data-body="Deleting an item will attempt to disconnect it from resources which depend on it. The object will be recoverable but dependencies won\'t." class="confirm awesome small red"');
                                }

                                if ($object->is_img) {

                                    echo anchor(cdn_serve($object->id), 'View', 'class="awesome small green fancybox"');

                                } else {

                                    echo anchor(cdn_serve($object->id), 'View', 'class="awesome small green fancybox" data-fancybox-type="iframe"');
                                }

                            echo '</td>';
                        echo '</tr>';
                    }

                } else {

                    echo '<tr>';
                        echo '<td colspan="10" class="no-data">';
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