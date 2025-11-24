<template>
    <ModalBase
        :visible="visible"
        title="Edit Bucket"
        @close="handleClose"
    >
        <template v-if="bucketToEdit">
            <form-group
                label="Display Name"
                for="bucket-label"
            >
                <input
                    type="text"
                    id="bucket-label"
                    v-model="bucketToEdit.label"
                    placeholder="Enter display name"
                    :disabled="isEditingBucket"
                />
            </form-group>
            <Status
                type="warning"
                message="The bucket's display name is visual only; changing this will NOT update the URL of files in this bucket."
            />
        </template>
        <template v-else>
            <Status
                type="error"
                message="No bucket selected for editing."
            />
        </template>
        <Status
            v-if="editBucketError"
            type="error"
            :message="editBucketError"
        />
        <Status
            v-if="editBucketSuccess"
            type="success"
            message="Bucket renamed successfully!"
        />
        <template #footer>
            <div
                :class="{
                    'safari-repaint-fix': true,
                    'run-fix': isEditingBucket
                }"
            >
                <Button
                    variant="secondary"
                    text="Cancel"
                    icon="cancel"
                    @click="handleClose"
                    :disabled="isEditingBucket"
                />
                <Button
                    variant="primary"
                    :text="isEditingBucket ? 'Saving...' : 'Save Changes'"
                    icon="save"
                    @click="handleConfirm"
                    :disabled="!bucketToEdit?.label || isEditingBucket"
                    :loading="isEditingBucket"
                />
            </div>
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import Status from '../Status.vue';
import FormGroup from "../Form/Group.vue";

export default {
    name: 'ModalEditBucket',
    components: {FormGroup, ModalBase, Button, Status},
    props: {
        visible: {type: Boolean, default: false},
        bucketToEdit: {type: Object, default: null},
        isEditingBucket: {type: Boolean, default: false},
        editBucketError: {type: String, default: null},
        editBucketSuccess: {type: Boolean, default: false}
    },
    methods: {
        handleClose() {
            this.$emit('close');
        },
        handleConfirm() {
            this.$emit('confirm');
        }
    }
};
</script>

<style scoped>

</style> 
