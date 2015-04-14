<div class="group-cdn object create">
    <p>
        Choose which bucket the items you wish to upload should belong to then drag files in (or click the area below).
    </p>
    <p class="system-alert message" id="alert-complete" style="display:none;">
        <strong>Complete.</strong> All files have been processed; any files which raised an error have been left in the list.
    </p>
    <fieldset>
        <legend>Bucket</legend>
        <?php

            $field          = array();
            $field['key']   = 'bucket_id';
            $field['label'] = 'Bucket';
            $field['class'] = 'select2';
            $field['id']    = 'bucket-chooser';

            $options = array();
            foreach ($buckets as $bucket) {

                $options[$bucket->slug] = $bucket->label;
            }

            echo form_field_dropdown($field, $options);

        ?>
    </fieldset>
    <div id="dropzone" class="dropzone dz-square"></div>
</div>
