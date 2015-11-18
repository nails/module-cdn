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

                    echo '<tr>';
                        echo '<td class="id">' . number_format($object->id) . '</td>';
                        echo '<td class="thumbnail">';

                            switch ($object->mime) {

                                case 'image/png' :
                                case 'image/jpeg' :
                                case 'image/gif' :

                                    echo anchor(cdnServe($object->id), img(cdnScale($object->id, 64, 64)), 'class="fancybox"');
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
                        echo adminHelper('loadUserCell', $object->creator);
                        echo adminHelper('loadDatetimeCell', $object->created);
                        echo adminHelper('loadDatetimeCell', $object->modified);
                        echo '<td class="filesize">' . format_bytes($object->filesize) . '</td>';
                        echo '<td class="actions">';

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

                        echo '</td>';
                    echo '</tr>';
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