<?php

/**
 * @var \Nails\Cdn\Resource\CdnObject       $oObject
 * @var \Nails\Cdn\Factory\Monitor\Detail[] $aLocations
 */

?>
<?=form_open(null, 'method="GET"')?>
<?=form_hidden('object', $oObject->id)?>
    <fieldset>
        <legend>Object</legend>
        <table class="table table-striped table-hover table-bordered table-responsive">
            <tbody>
                <tr>
                    <?php

                    if ($oObject->is_img) {
                        ?>
                        <td rowspan="6" style="width: 200px;">
                            <a href="<?=cdnServe($oObject->id)?>" class="fancybox">
                                <img src="<?=cdnScale($oObject->id, 200, 200)?>" style="max-width: 100%;" />
                            </a>
                        </td>
                        <?php
                    }

                    ?>
                    <td style="width: 150px;"><strong>ID</strong></td>
                    <td><?=$oObject->id?></td>
                </tr>
                <tr>
                    <td style="width: 150px;"><strong>Filename</strong></td>
                    <td><?=$oObject->file->name->human?></td>
                </tr>
                <tr>
                    <td style="width: 150px;"><strong>Filename (on disk)</strong></td>
                    <td><?=$oObject->file->name->disk?></td>
                </tr>
                <tr>
                    <td style="width: 150px;"><strong>MIME</strong></td>
                    <td><?=$oObject->file->mime?></td>
                </tr>
                <tr>
                    <td style="width: 150px;"><strong>Size</strong></td>
                    <td><?=$oObject->file->size->human?></td>
                </tr>
                <tr>
                    <td style="width: 150px;"><strong>Driver</strong></td>
                    <td><?=$oObject->driver?></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
    <fieldset>
        <legend>Locations</legend>
        <table class="table table-striped table-hover table-bordered table-responsive">
            <thead class="table-dark">
                <tr>
                    <th>Monitor</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (!empty($aLocations)) {
                    /** @var \Nails\Cdn\Factory\Monitor\Detail $oDetail */
                    foreach ($aLocations as $oDetail) {
                        ?>
                        <tr>
                            <td>
                                <?=$oDetail->getMonitor()->getLabel()?>
                            </td>
                            <td>
                                <code style="padding: 10px; display: block; white-space: pre"><?=json_encode($oDetail->getData(), JSON_PRETTY_PRINT)?></code>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="2" class="no-data">
                            This object does not seem to be in use
                        </td>
                    </tr>
                    <?php
                }

                ?>
            </tbody>
        </table>
    </fieldset>
<?php

if (!empty($aLocations)) {
    ?>
    <fieldset>
        <legend>Actions</legend>
        <?php

        echo form_field_dropdown([
            'key'     => 'action',
            'label'   => 'Action',
            'class'   => 'select2',
            'options' => [
                'delete'  => 'Remove references',
                'replace' => 'Replace references',
            ],
            'data'    => [
                'revealer' => 'usage-action',
            ],
        ]);

        echo form_field_cdn_object_picker([
            'key'   => 'replacement',
            'label' => 'Replacement',
            'class' => 'select2',
            'data'  => [
                'revealer'  => 'usage-action',
                'reveal-on' => 'replace',
            ],
        ]);

        ?>
    </fieldset>
    <?php

    echo \Nails\Admin\Helper::floatingControls([
        'save' => [
            'text'  => 'Confirm',
            'class' => 'btn btn-danger',
        ],
    ]);
}
echo form_close();
