<template>
    <ModalBase
        :visible="visible"
        title="Delete Bucket"
        @close="handleClose"
    >
        <template v-if="bucketToDelete">
            <Status
                type="warning"
                :title="'Are you sure you want to delete this bucket?'"
                :message="'The following bucket will be deleted:<br><strong>' + bucketToDelete.label + ' (ID: ' + bucketToDelete.id + ')</strong><br>This action cannot be undone.'"
            />
        </template>
        <template v-else>
            <Status
                type="error"
                message="No bucket selected for deletion."
            />
        </template>
        <Status
            v-if="deleteBucketError"
            type="error"
            :message="deleteBucketError"
        />
        <Status
            v-if="deleteBucketSuccess"
            type="success"
            message="Bucket deleted successfully!"
        />
        <template #footer>
            <div
                :class="{
                    'safari-repaint-fix': true,
                    'run-fix': isDeletingBucket
                }"
            >
                <Button
                    variant="secondary"
                    text="Cancel"
                    icon="cancel"
                    @click="handleClose"
                    :disabled="isDeletingBucket"
                />
                <Button
                    variant="danger"
                    :text="isDeletingBucket ? 'Deleting...' : 'Delete Bucket'"
                    icon="delete"
                    @click="handleConfirm"
                    :disabled="isDeletingBucket"
                    :loading="isDeletingBucket"
                />
            </div>
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import Status from '../Status.vue';

export default {
    name: 'ModalDeleteBucket',
    components: {ModalBase, Button, Status},
    props: {
        visible: {type: Boolean, default: false},
        bucketToDelete: {type: Object, default: null},
        isDeletingBucket: {type: Boolean, default: false},
        deleteBucketError: {type: String, default: null},
        deleteBucketSuccess: {type: Boolean, default: false}
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
