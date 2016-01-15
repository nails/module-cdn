<div class="group-utilities cdn orphans">
    <p>
        It is possible for CDN objects in the database to become disconnected from the physical files on disk.
        If you notice files seem to be missing when they shouldn't (e.g error triangle or 404s) then use this utlity
        to find broken objects.
    </p>
    <p>
        You can choose to specify wether to look for database items which are missing files, or the opposite,
        files which aren't in the database.
    </p>
    <p class="alert alert-warning">
        <strong>Please note:</strong> This process can take some time to execute on large CDNs and may time out. If
        you are experiencing timeouts consider increasing the timeout limit for PHP temporarily or executing
        <u rel="tipsy" title="Use command: `php index.php admin cdn utilities index`">via the command line</u>.
    </p>
    <hr />
    <?=form_open(NULL, 'id="search-form"')?>
    <fieldset>
        <legend>Search Options</legend>
        <?php

        $aField          = array();
        $aField['key']   = 'type';
        $aField['label'] = 'Search For';
        $aField['class'] = 'select2';

        $aOptions = array(
            'db'   => 'Database objects for which the file does not exist.',
            'file' => 'Files which do not exist in the database.'
       );

        echo form_field_dropdown($aField, $aOptions);

        // --------------------------------------------------------------------------

        $aField          = array();
        $aField['key']   = 'parser';
        $aField['label'] = 'With the results';
        $aField['class'] = 'select2';

        $aOptions = array(
            'list'   => 'Show list of results',
            'purge'  => 'Permanently delete',
            'create' => 'Add to database (applicable to File search only)'
       );

        echo form_field_dropdown($aField, $aOptions);

        ?>
    </fieldset>
    <?=form_submit('submit', lang('action_search'), 'class="btn btn-primary"')?>
    <?=form_close()?>
    <?php

    if (isset($orphans)) {

        ?>
        <hr />
        <h2>
            Results <?=!empty($orphans['elapsed_time']) ? '(search took ' . $orphans['elapsed_time'] . ' seconds)' : '' ?>
        </h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th>Filename</th>
                        <th>Filesize</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php

                if (!empty($orphans['orphans'])) {

                    foreach ($orphans['orphans'] as $orphan) {

                        ?>
                        <tr>
                            <td><?=$orphan->bucket?></td>
                            <td><?=$orphan->file->name->human?></td>
                            <td><?=$orphan->file->size->human?></td>
                            <td>
                            <?php

                            $aAttr = array(
                                'data-title="Are you sure?"',
                                'data-body="This action is permanent and cannot be undone."',
                                'class="confirm btn btn-xs btn-danger"'
                            );

                            if (!empty($orphan->id)) {

                                echo anchor(
                                    '#',
                                    lang('action_delete'),
                                    implode(' ', $aAttr)
                                );

                            } else {

                                echo anchor(
                                    '#',
                                    lang('action_delete'),
                                    implode(' ', $aAttr)
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
                        <td colspan="4" class="no-data">
                            No orphaned items were found.
                        </td>
                    </tr>
                    <?php
                }

                ?>
                </tbody>
            </table>
        </div>
        <?php

    }

    ?>
    <div id="search-mask" class="mask"></div>
</div>
