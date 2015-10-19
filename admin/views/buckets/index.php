<div class="group-cdn buckets browse">
    <p>
        The following buckets are available on this site.
    </p>
    <?=\Nails\Admin\Helper::loadSearch($search)?>
    <?=\Nails\Admin\Helper::loadPagination($pagination)?>
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

                    ?>
                    <tr>
                        <td class="id"><?=number_format($bucket->id)?></td>
                        <td class="label"><?=$bucket->label?></td>
                        <td class="count"><?=$bucket->objectCount?></td>
                        <?=\Nails\Admin\Helper::loadUserCell($bucket->creator)?>
                        <?=\Nails\Admin\Helper::loadDatetimeCell($bucket->created)?>
                        <td class="actions">
                            <?php

                            if (userHasPermission('admin:cdn:objects:browse')) {

                                echo anchor(
                                    'admin/cdn/objects/index?bucketId=' . $bucket->id . $return,
                                    'Browse',
                                    'class="btn btn-xs btn-primary"'
                                );
                            }

                            if (userHasPermission('admin:cdn:buckets:edit')) {

                                echo anchor(
                                    'admin/cdn/buckets/edit/' . $bucket->id . $return,
                                    'Edit',
                                    'class="btn btn-xs btn-primary"'
                                );
                            }

                            if (userHasPermission('admin:cdn:buckets:delete')) {

                                echo anchor(
                                    'admin/cdn/buckets/delete/' . $bucket->id . $return,
                                    'Delete',
                                    'data-title="Are you sure?" data-body="All objects contained within a bucket will be orphaned. This cannot be undone." class="confirm btn btn-xs btn-danger"'
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
                    <td colspan="6" class="no-data">
                        No Buckets Found
                    </td>
                </tr>
                <?php

            }

            ?>
            </tbody>
        </table>
    </div>
    <?=\Nails\Admin\Helper::loadPagination($pagination)?>
</div>