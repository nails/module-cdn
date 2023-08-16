<?php

/**
 * @var \DateTime                       $oBegin
 * @var \Nails\Cdn\Resource\CdnObject[] $aObjects
 */

if (!empty($oBegin) || !empty($aObjects)) {

    ?>
    <div class="cdn cdn-unused">
        <div class="alert alert-warning">
            <p>
                ⚠️ &nbsp; The data below is produced using data generated on
                <strong><?=toUserDateTime($oBegin)?></strong>
            </p>
        </div>
        <?php

        if (count($aIds) > count($aObjects)) {
            ?>
            <div class="alert alert-info">
                <p>
                    ⚠️ &nbsp; For performance reasons only showing the first <?=count($aObjects)?> of
                    <?=number_format(count($aIds))?> unused objects.
                </p>
            </div>
            <?php
        }

        ?>
        <table>
            <thead>
                <tr>
                    <th class="text-center">ID</th>
                    <th>Preview</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Bucket</th>
                    <th>Created</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (!empty($aObjects)) {

                    foreach ($aObjects as $oObject) {

                        ?>
                        <tr>
                            <td class="text-center"><?=$oObject->id?></td>
                            <td class="text-center">
                                <?php

                                if ($oObject->is_img) {
                                    echo anchor(
                                        cdnServe($oObject->id),
                                        img(
                                            [
                                                'src' => cdnCrop($oObject->id, 50, 50),
                                                'alt' => $oObject->file->name->human,
                                            ]
                                        ),
                                        'class="fancybox"'
                                    );
                                } else {
                                    echo '<span class="text-muted">&mdash;</span>';
                                }

                                ?>
                            </td>
                            <td><?=anchor(cdnServe($oObject->id), $oObject->file->name->human, 'target="_blank"')?></td>
                            <td><?=$oObject->file->mime?></td>
                            <td><?=$oObject->file->size->human?></td>
                            <td>
                                <?=$oObject->bucket->label?>
                                <small><code><?=$oObject->bucket->slug?></code></small>
                            </td>
                            <?=\Nails\Admin\Helper::loadDateTimeCell($oObject->created)?>
                            <td class="actions">
                                <a href="<?=siteUrl('admin/cdn/utilities/unused/' . $oObject->id . '/delete')?>" class="btn btn-xs btn-danger confirm">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php
                    }

                } else {
                    ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            No unused objects found
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
