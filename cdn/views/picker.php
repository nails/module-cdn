<div class="cdn-object-picker" data-bucket="<?=$bucketSlug?>" <?=$attributes?>>
    <input class="cdn-object-picker__input" type="hidden" name="<?=$key?>" value="<?=$objectId?>" <?=$inputAttributes?>>
    <button type="button" class="cdn-object-picker__btn btn btn-sm btn-primary" <?=$isReadOnly ? 'disabled' : ''?>>
        Browse
    </button>
    <b class="cdn-object-picker__pending fa fa-spinner fa-spin"></b>
    <div class="cdn-object-picker__label"></div>
    <a class="cdn-object-picker__preview-link" href="#">
        <div class="cdn-object-picker__preview"></div>
    </a>
    <b class="cdn-object-picker__remove fa fa-times-circle"></b>
</div>
