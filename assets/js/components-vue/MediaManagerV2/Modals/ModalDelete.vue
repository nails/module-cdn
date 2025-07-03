<template>
    <ModalBase
        :visible="visible"
        title="Delete File"
        @close="handleClose"
    >
        <template v-if="deletingObject">
            <FilePreview
                :file="deletingObject"
                :show-border="true"
                :margin-bottom="true"
            />
            <Status
                v-if="true"
                type="warning"
                :message="'Are you sure you want to delete this file?'"
            />
        </template>
        <template v-else>
            <Status
                type="error"
                message="No file selected for deletion."
            />
        </template>
        <Status
            v-if="deleteError"
            type="error"
            :message="deleteError"
        >
            <template v-if="deleteError && deleteError.includes('Object is in use') && deletingObject">
                <a
                    :href="`${siteUrl}/admin/cdn/utilities/usages?object=${deletingObject.id}`"
                    target="_blank"
                    class="check-usages-button"
                >
                    Check for usages
                </a>
            </template>
        </Status>
        <Status
            v-if="deleteSuccess"
            type="success"
            message="Object deleted successfully!"
        />
        <template #footer>
            <Button
                variant="secondary"
                text="Cancel"
                icon="cancel"
                @click="handleClose"
                :disabled="isDeleting"
            />
            <Button
                variant="danger"
                :text="isDeleting ? 'Deleting...' : 'Delete File'"
                icon="delete"
                @click="handleConfirm"
                :disabled="isDeleting"
                :loading="isDeleting"
            />
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import FilePreview from '../FilePreview.vue';
import Status from '../Status.vue';

export default {
    name: 'ModalModal',
    components: {ModalBase: ModalBase, Button, FilePreview, Status},
    props: {
        visible: {type: Boolean, default: false},
        deletingObject: {type: Object, default: null},
        isDeleting: {type: Boolean, default: false},
        deleteError: {type: String, default: null},
        deleteSuccess: {type: Boolean, default: false},
        siteUrl: {type: String, default: ''}
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
.check-usages-button {
    display: inline-block;
    margin-left: 0.5rem;
    color: #4f46e5;
    text-decoration: none;
    font-weight: 500;
}

.check-usages-button:hover {
    text-decoration: underline;
}
</style> 
