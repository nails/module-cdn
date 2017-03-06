<?php

use Nails\Factory;

$oInput = Factory::service('Input');

?>
<div class="group-cdn object create">
    <p>
        Choose which bucket the items you wish to upload should belong to then drag files in (or click the area below).
    </p>
    <p class="alert alert-warning" id="alert-complete" style="display:none;">
        <strong>Complete.</strong> All files have been processed; any files which raised an error have been left in the
        list.
    </p>
    <fieldset>
        <legend>Bucket</legend>
        <?php

        $aField = [
            'key'     => 'bucket_id',
            'label'   => 'Bucket',
            'class'   => 'select2',
            'id'      => 'bucket-chooser',
            'default' => $oInput->get('bucket'),
            'options' => [],
        ];

        foreach ($buckets as $bucket) {
            $aField['options'][$bucket->slug] = $bucket->label;
        }

        echo form_field_dropdown($aField);

        ?>
    </fieldset>
    <div id="dropzone" class="dropzone dz-square"></div>
</div>
