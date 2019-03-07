/* globals domElement */
var $fieldScale = $('select[name=scaling]', domElement).closest('.field');
var $fieldSize = $('select[name=size]', domElement).closest('.field');
var $fieldUrl = $('input[name=url]', domElement).closest('.field');
var $fieldTarget = $('select[name=target]', domElement).closest('.field');
var $fieldAttr = $('input[name=link_attr]', domElement).closest('.field');
var $inpScaling = $('select[name=scaling]', domElement);
var $inpLinking = $('select[name=linking]', domElement);

// --------------------------------------------------------------------------

/**
 * Hide all the things
 */
$fieldSize.hide();
$fieldUrl.hide();
$fieldTarget.hide();
$fieldAttr.hide();

// --------------------------------------------------------------------------

/*
 * Refresh the picker
 */
$('.cdn-object-picker', domElement).trigger('refresh');

// --------------------------------------------------------------------------

/**
 * Bind to the change event of the scale and link fields
 */
$inpScaling
    .on('change', function() {

        $fieldScale.find('.alert').hide();
        $fieldScale.find('.alert.' + $(this).val().toLowerCase()).show();

        switch ($(this).val()) {
            case 'CROP' :
                $fieldSize.show();
                $fieldSize.show();
                break;

            default :
                $fieldSize.hide();
                break;
        }
    })
    .trigger('change');

$inpLinking
    .on('change', function() {
        switch ($(this).val()) {
            case 'FULLSIZE' :
                $fieldUrl.hide();
                $fieldTarget.show().trigger('change');
                $fieldAttr.show();
                break;

            case 'CUSTOM' :
                $fieldUrl.show();
                $fieldTarget.show().trigger('change');
                $fieldAttr.show();
                break;

            default :
                $fieldUrl.hide();
                $fieldTarget.hide();
                $fieldAttr.hide();
                break;
        }
    })
    .trigger('change');
