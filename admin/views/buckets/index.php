<?php

    parse_str($this->input->server('QUERY_STRING'), $query);
    $query = array_filter($query);
    $query = $query ? '?' . http_build_query($query) : '';
    $return = $query ? '?return=' . urlencode(uri_string() . $query) : '';

?>
<div class="group-cdn buckets browse">
    <p>
        The following buckets are available on this site.
        <?php

            if (userHasPermission('admin.cdnadmin:0.can_create_buckets')) {

                echo anchor('admin/cdn/buckets/create' . $return, 'Create Bucket', 'style="float:right" class="awesome small green"');

            }

        ?>
    </p>
    <hr />
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

                            if (userHasPermission('admin.cdnadmin:0.can_edit_buckets')) {

                                echo anchor('admin/cdn/buckets/edit/' . $bucket->id . $return, 'Edit', 'class="awesome small"');
                            }

                            if (userHasPermission('admin.cdnadmin:0.can_delete_objects')) {

                                echo anchor('admin/cdn/buckets/delete/' . $bucket->id . $return, 'Delete', 'data-title="Are you sure?" data-body="All objects contained within a bucket will be orphaned. This cannot be undone." class="confirm awesome small red"');
                            }

                        echo '</td>';
                    echo '</tr>';
                }

            } else {

                echo '<tr>';
                    echo '<td colspan="5" class="no-data">';
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