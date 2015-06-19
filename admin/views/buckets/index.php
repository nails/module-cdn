<div class="group-cdn buckets browse">
    <p>
        The following buckets are available on this site.
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
                    <th class="label">Label</th>
                    <th class="count">Objects</th>
                    <th class="datetime">Created</th>
                    <th class="user">Created By</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php

                if ($buckets) {

                    foreach ($buckets as $bucket) {

                        echo '<tr>';
                            echo '<td class="id">' . number_format($bucket->id) . '</td>';
                            echo '<td class="label">' . $bucket->label . '</td>';
                            echo '<td class="count">' . $bucket->objectCount . '</td>';
                            echo \Nails\Admin\Helper::loadUserCell($bucket->creator);
                            echo \Nails\Admin\Helper::loadDatetimeCell($bucket->created);
                            echo '<td class="actions">';

                                if (userHasPermission('admin:cdn:objects:browse')) {

                                    echo anchor('admin/cdn/objects/index?bucketId=' . $bucket->id . $return, 'Browse', 'class="awesome small"');
                                }

                                if (userHasPermission('admin:cdn:buckets:edit')) {

                                    echo anchor('admin/cdn/buckets/edit/' . $bucket->id . $return, 'Edit', 'class="awesome small"');
                                }

                                if (userHasPermission('admin:cdn:buckets:delete')) {

                                    echo anchor('admin/cdn/buckets/delete/' . $bucket->id . $return, 'Delete', 'data-title="Are you sure?" data-body="All objects contained within a bucket will be orphaned. This cannot be undone." class="confirm awesome small red"');
                                }

                            echo '</td>';
                        echo '</tr>';
                    }

                } else {

                    echo '<tr>';
                        echo '<td colspan="6" class="no-data">';
                            echo 'No Buckets Found';
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