<?php

use Nails\Admin\Helper;
use Nails\Cdn\Constants;
use Nails\Cdn\Resource\CdnObject;

/**
 * @var DateTime    $oLastStarted
 * @var CdnObject[] $aObjects
 * @var array       $search
 * @var array       $pagination
 */

if (!empty($oLastStarted) || !empty($aObjects)) {

    ?>
    <div class="cdn cdn-unused">
        <?php

        if (appSetting('cdn:monitor:unused:lasterror', Constants::MODULE_SLUG)) {
            ?>
            <div class="alert alert-danger">
                <p>
                    ⛔️ &nbsp; An error was encountered during the last scan:
                <pre style="padding: 1rem;margin-top:1rem"><?=appSetting('cdn:monitor:unused:lasterror', Constants::MODULE_SLUG)?></pre>
                </p>
            </div>
            <?php
        }

        ?>
        <div class="alert alert-warning">
            <p>
                ⚠️ &nbsp; The data below is produced using data generated on
                <strong><?=toUserDateTime($oLastStarted)?></strong>
            </p>
        </div>
        <?php

        echo adminHelper('loadSearch', $search);
        echo form_open();
        echo adminHelper('loadPagination', $pagination);

        ?>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width:50px;">
                        <input type="checkbox" id="cdn-unused-ids-select-all" />
                    </th>
                    <th class="text-center" style="width:75px;">ID</th>
                    <th>Preview</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Bucket</th>
                    <th>Created</th>
                    <th>Unused Since</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                if (!empty($aObjects)) {

                    foreach ($aObjects as $oObject) {

                        ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?=$oObject->id?>" class="cdn-unused-ids" />
                            </td>
                            <td class="text-center">
                                <?=$oObject->id?>
                            </td>
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
                            <?=Helper::loadDateTimeCell($oObject->created)?>
                            <?=Helper::loadDateTimeCell($oObject->unusedSince())?>
                            <td class="actions">
                                <a href="<?=siteUrl('admin/cdn/utilities/unused/' . $oObject->id . '/delete?return=' . urlencode($_SERVER['REQUEST_URI'] ?? ''))?>" class="btn btn-xs btn-danger confirm">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php
                    }

                } else {
                    ?>
                    <tr>
                        <td colspan="10" class="no-data">
                            No unused objects found
                        </td>
                    </tr>
                    <?php
                }

                ?>
            </tbody>
        </table>
        <?php

        echo adminHelper('loadPagination', $pagination);

        if (count($aObjects) > 0) {
            echo form_hidden('return', urlencode($_SERVER['REQUEST_URI'] ?? ''));
            echo Helper::floatingControls([
                'save' => [
                    'text'  => 'Delete Selected',
                    'class' => 'btn btn-danger',
                ],
            ]);
        }
        echo form_close();

        ?>
    </div>
    <script>

    const selectAll = document.querySelector('#cdn-unused-ids-select-all');
    const checkboxes = document.querySelectorAll('.cdn-unused-ids');

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            if (!cb.checked) {
                selectAll.checked = false;
            } else if (document.querySelectorAll('.cdn-unused-ids:checked').length === checkboxes.length) {
                selectAll.checked = true;
            }
        });
    });

    </script>
    <?php
}
