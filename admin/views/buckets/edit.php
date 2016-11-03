<div class="group-cdn bucket edit">
    <?=form_open()?>
    <fieldset>
        <legend>
            Basic Details
        </legend>
        <?php

        $aField = [
            'key'      => 'label',
            'label'    => 'Label',
            'default'  => isset($item->label) ? $item->label : null
        ];
        echo form_field($aField);

        $aField = [
            'key'      => 'allowed_types',
            'label'    => 'Allowed File Types',
            'note'     => 'Comma separated list of acceptable file extensions',
            'default'  => isset($item->allowed_types) ? $item->allowed_types : null
        ];
        echo form_field($aField);

        $aField = [
            'key'      => 'max_size',
            'label'    => 'Max Size',
            'note'     => 'Maximum size of an individual file',
            'default'  => isset($item->max_size) ? $item->max_size : null,
            'data'     => [
                'step' => 1
            ]
        ];
        echo form_field_number($aField);

        $aField = [
            'key'      => 'disc_quota',
            'label'    => 'Disk Quota',
            'note'     => 'Maximum disk space which can be used by the bucket',
            'default'  => isset($item->disc_quota) ? $item->disc_quota : null,
            'data'     => [
                'step' => 1
            ]
        ];
        echo form_field_number($aField);

        ?>
    </fieldset>
    <p>
        <button type="submit" class="btn btn-primary">
            Save Changes
        </button>
    </p>
    <?=form_close()?>
</div>
