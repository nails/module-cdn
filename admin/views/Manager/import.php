<?php

use Nails\Cdn\Resource\CdnObject\Import;

/**
 * @var string   $sMaxUploadSize
 * @var string[] $aBuckets
 * @var bool     $bImporAccepted
 * @var Import[] $aImports
 */

?>
<div class="module-cdn large">
    <?php

    if ($bImportAccepted) {
        ?>
        <div class="alert alert-success">
            <p>
                <strong>Import Accepted</strong>
                <br>The URL was accepted by the system and will be imported momentarily.
            </p>
        </div>
        <?php
    }

    ?>
    <p>
        The maximum uplaod size accepted by this server is <?=$sMaxUploadSize?>. To circumvent this limit,
        you may import large files via a publicly accessible URL. Once imported, the file may be selected
        in the <?=anchor('admin/cdn/manager/index', 'Media Manager')?>.
    </p>
    <?=form_open()?>
    <fieldset>
        <legend>Source File</legend>
        <?php

        echo form_field_url([
            'key'   => 'url',
            'label' => 'Public URL',
        ]);

        echo form_field_dropdown([
            'key'     => 'bucket_id',
            'label'   => 'Bucket',
            'class'   => 'select2',
            'options' => $aBuckets,
        ]);

        ?>
    </fieldset>
    <p>
        <button tyoe="submit" class="btn btn-primary">
            Import
        </button>
    </p>
    <?=form_close()?>
    <?php
    if (!empty($aImports)) {
        ?>
        <hr>
        <fieldset>
            <legend>Recent Imports</legend>
            <p class="alert alert-info">
                The following imports have been requested by you in the past 24 hours.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Bucket</th>
                        <th class="text-center">Status</th>
                        <th>Accepted</th>
                        <th>Modified</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($aImports as $oImport) {
                        ?>
                        <tr>
                            <td>
                                <?=anchor($oImport->url, null, 'target="_blank"')?>
                                <small>
                                    <?php
                                    echo implode(', ', array_filter([
                                        $oImport->mime,
                                        formatBytes($oImport->size),
                                    ]));
                                    ?>
                                </small>
                            </td>
                            <td><?=$oImport->bucket->label?></td>
                            <?php

                            switch ($oImport->status) {
                                case \Nails\Cdn\Model\CdnObject\Import::STATUS_PENDING:
                                    $sClass = 'warning';
                                    $sText  = 'Pending';
                                    break;
                                    break;

                                case \Nails\Cdn\Model\CdnObject\Import::STATUS_IN_PROGRESS:
                                    $sClass = 'info';
                                    $sText  = 'In Progress';
                                    break;

                                case \Nails\Cdn\Model\CdnObject\Import::STATUS_COMPLETE:
                                    $sClass = 'success';
                                    $sText  = 'Complete';
                                    break;

                                case \Nails\Cdn\Model\CdnObject\Import::STATUS_ERROR:
                                    $sClass = 'danger';
                                    $sText  = 'Error';
                                    break;

                                case \Nails\Cdn\Model\CdnObject\Import::STATUS_CANCELLED:
                                    $sClass = 'danger';
                                    $sText  = 'Cancelled';
                                    break;
                            }
                            ?>
                            <td class="text-center <?=$sClass?>">
                                <?=$sText?>
                                <?=$oImport->error ? '<small>' . $oImport->error . '</small>' : ''?>
                            </td>
                            <?php

                            echo \Nails\Admin\Helper::loadDateTimeCell($oImport->created);
                            echo \Nails\Admin\Helper::loadDateTimeCell($oImport->modified);

                            echo '<td class="actions">';
                            if ($oImport->status === \Nails\Cdn\Model\CdnObject\Import::STATUS_PENDING) {
                                echo anchor(
                                    'admin/cdn/manager/import/cancel/' . $oImport->id,
                                    'Cancel',
                                    'class="btn btn-danger btn-xs"'
                                );
                            }
                            echo '</td>';
                            ?>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </fieldset>
        <?php
    }
    ?>
</div>
