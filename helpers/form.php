<?php

use Nails\Cdn\Helper\Form;

if (!function_exists('form_field_cdn_object_picker')) {
    function form_field_cdn_object_picker($field, $tip = ''): string
    {
        return Form::form_field_cdn_object_picker($field, $tip);
    }
}

if (!function_exists('form_field_cdn_object_picker_multi')) {
    function form_field_cdn_object_picker_multi($field, $tip = ''): string
    {
        return Form::form_field_cdn_object_picker_multi($field, $tip);
    }
}

if (!function_exists('form_field_cdn_object_picker_multi_with_label')) {
    function form_field_cdn_object_picker_multi_with_label($aConfig, $sTip = ''): string
    {
        return Form::form_field_cdn_object_picker_multi_with_label($aConfig, $sTip);
    }
}

