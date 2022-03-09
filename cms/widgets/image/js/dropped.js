/* globals domElement */
var $fieldScale = $('select[name=sScaling]', domElement).closest('.field');
var $fieldSize = $('select[name=sSize]', domElement).closest('.field');
var $fieldUrl = $('input[name=sUrl]', domElement).closest('.field');
var $fieldTarget = $('select[name=sTarget]', domElement).closest('.field');
var $fieldAttr = $('input[name=sLinkAttr]', domElement).closest('.field');
var $inpScaling = $('select[name=sScaling]', domElement);
var $inpLinking = $('select[name=sLinking]', domElement);

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
            case 'SCALE' :
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
