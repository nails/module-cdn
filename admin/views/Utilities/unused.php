<?php

if (!empty($oBegin) || !empty($aIds)) {

    ?>
    <div class="cdn cdn-unused">
        <div class="alert alert-warning">
            <p>
                ⚠️ &nbsp; The data below is produced using data generated on
                <strong><?=toUserDateTime($oBegin)?></strong>
            </p>
        </div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">ID</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (!empty($aIds)) {

                    $oModel = \Nails\Factory::model('Object', \Nails\Cdn\Constants::MODULE_SLUG);

                    foreach ($aIds as $iId) {

                        /** @var \Nails\Cdn\Resource\CdnObject $oObject */
                        $oObject = $oModel->getById($iId);

                        ?>
                        <tr>
                            <td class="text-center"><?=$oObject->id?></td>
                            <td><?=anchor(cdnServe($oObject->id), $oObject->file->name->human, 'target="_blank"')?></td>
                            <td><?=$oObject->file->mime?></td>
                            <td><?=$oObject->file->size->human?></td>
                            <td class="actions"></td>
                        </tr>
                        <?php
                    }

                } else {
                    ?>
                    <tr>
                        <td colspan="2" class="no-data">
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
