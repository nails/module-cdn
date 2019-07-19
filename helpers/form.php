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

if (!function_exists('form_field_mm')) {
    function form_field_mm($aField, $sTip = ''): string
    {
        return Form::form_field_mm($aField, $sTip);
    }
}

if (!function_exists('form_field_mm_image')) {
    function form_field_mm_image($aField, $sTip = ''): string
    {
        return Form::form_field_mm_image($aField, $sTip);
    }
}
