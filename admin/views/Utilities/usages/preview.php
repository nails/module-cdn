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
        <table>
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
        <table>
            <thead>
                <tr>
                    <th>Monitor</th>
                    <th>Details</th>
                    <th class="actions" style="width:175px;">Actions</th>
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
                            <?php

                            if ($oDetail->getActions()) {
                                ?>
                                <td class="actions">
                                    <?php

                                    foreach ($oDetail->getActions() as $oAction) {

                                        $aAttr = [
                                            'target' => $oAction->getTarget(),
                                            'class'  => 'btn btn-xs ' . $oAction->getClass(),
                                        ];

                                        if ($oAction->isConfirm()) {
                                            $aAttr['class'] .= ' confirm';
                                            if ($oAction->getConfirmTitle()) {
                                                $aAttr['data-title'] = $oAction->getConfirmTitle();
                                            }
                                            if ($oAction->getConfirmBody()) {
                                                $aAttr['data-body'] = $oAction->getConfirmBody();
                                            }
                                        }

                                        echo anchor(
                                            $oAction->getUrl(),
                                            $oAction->getLabel(),
                                            $aAttr
                                        );
                                    }

                                    ?>
                                </td>
                                <?php

                            } else {
                                //  CSS :empty requires totally empty cell
                                echo '<td class="actions"></td>';
                            }

                            ?>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="3" class="no-data">
                            This object does not seem to be in use
                        </td>
                    </tr>
                    <?php
                }

                ?>
            </tbody>
        </table>
    </fieldset>
    <fieldset>
        <legend>Actions</legend>
        <?php

        echo form_field_dropdown([
            'key'     => 'action',
            'label'   => 'Action',
            'class'   => 'select2',
            'options' => array_filter([
                'delete'        => !empty($aLocations) ? 'Remove references' : null,
                'replace'       => !empty($aLocations) ? 'Replace references' : null,
                'delete-object' => empty($aLocations) ? 'Delete File' : null,
            ]),
            'data'    => [
                'revealer' => 'usage-action',
            ],
            'info' => <<<EOT
            <p class="alert alert-info" data-revealer="usage-action" data-reveal-on="delete">
            Removing references erases the link between the atatched entities and the object. In most cases this fully disconnects the oject, allowing it to be deleted.
            </p>
            <p class="alert alert-info" data-revealer="usage-action" data-reveal-on="replace">
            When replacing references, the attached entities are updated to point to the replacement file. The old file then becomes fully disconnected, allowing it to be deleted.
            </p>
            <p class="alert alert-info" data-revealer="usage-action" data-reveal-on="delete-object">
            Deleting a file is available to objects which are not in use. Performing this action moves the object to the trash.
            </p>
            EOT
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

echo form_close();
