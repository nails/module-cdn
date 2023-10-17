<?=form_open(null, 'method="GET"')?>
    <fieldset>
        <legend>Object</legend>
        <?=cdnObjectPicker('object', null)?>
    </fieldset>
<?php

echo \Nails\Admin\Helper::floatingControls([
    'save' => [
        'text'  => 'Find Usages',
        'class' => 'btn btn-success',
    ],
]);
echo form_close();
