<template>
    <!--
        The server markup from cdn/views/picker.php. Only the hidden input is bound by Vue; the label and preview are
        filled in by the picker's own code (components/ObjectPicker.js), so they are left empty here.
    -->
    <div ref="picker" class="cdn-object-picker" :data-bucket="field.bucket || null">
        <input class="cdn-object-picker__input" type="hidden" :value="value ?? ''">
        <button type="button" class="cdn-object-picker__btn btn btn-sm btn-primary">Browse</button>
        <b class="cdn-object-picker__pending fa fa-spinner fa-spin"></b>
        <div class="cdn-object-picker__label"></div>
        <a class="cdn-object-picker__preview-link" href="#">
            <div class="cdn-object-picker__preview"></div>
        </a>
        <b class="cdn-object-picker__remove fa fa-times-circle"></b>
    </div>
</template>

<script>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * The widget editor control for a `cdn_object` field: admin's object picker, kept in step with the stored id
 */
export default {
    name: 'ObjectPickerControl',
    props: {
        field: { type: Object, required: true },
        value: { type: null, default: undefined },
    },
    emits: ['update'],
    setup(props, { emit }) {
        const picker = ref(null);

        // The picker's handlers set the hidden input and trigger these events. Listening through jQuery is the
        // only way to see them, because they are not DOM events.
        let onPicked = null;
        // Set while the value is changed from outside, so the picker's own events are not written back as an edit
        let syncing = false;

        function input() {
            return picker.value ? picker.value.querySelector('.cdn-object-picker__input') : null;
        }

        /**
         * Brings the picker in line with the stored value: fetches the object's name and thumbnail when there is
         * one, or clears the picker when the value was emptied from outside (e.g. the row above was removed).
         */
        function sync() {
            const $ = window.jQuery;
            if (!$ || !picker.value) {
                return;
            }
            const field = input();
            const wanted = props.value ? String(props.value) : '';

            syncing = true;
            if (wanted) {
                $(picker.value).trigger('refresh');
            } else if (field && field.value !== '') {
                $(picker.value).find('.cdn-object-picker__remove').trigger('click');
            }
            syncing = false;
        }

        onMounted(() => {
            if (!picker.value || !window.jQuery) {
                return;
            }
            onPicked = () => {
                if (!syncing) {
                    const field = input();
                    emit('update', field ? field.value : null);
                }
            };
            window.jQuery(picker.value).on('picked reset', onPicked);
            sync();
        });

        watch(
            () => props.value,
            () => {
                const field = input();
                const wanted = props.value ? String(props.value) : '';
                if (field && field.value !== wanted) {
                    sync();
                }
            }
        );

        onBeforeUnmount(() => {
            if (onPicked && picker.value && window.jQuery) {
                window.jQuery(picker.value).off('picked reset', onPicked);
            }
        });

        return { picker };
    },
};
</script>
