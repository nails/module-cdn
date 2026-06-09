<template>
    <ModalBase
        :visible="visible"
        title="Delete File"
        :locked="isDeleting"
        @close="handleClose"
    >
        <template v-if="deletingObject">
            <FilePreview
                :file="deletingObject"
                :show-border="true"
                :margin-bottom="true"
            />
            <Status
                type="warning"
                message="Are you sure you want to delete this file?"
            />
        </template>
        <template v-else>
            <Status
                type="error"
                message="No file selected for deletion."
            />
        </template>
        <Status
            v-if="deleteErrorMessage"
            type="error"
            :message="deleteErrorMessage"
        />
        <Status
            v-if="deleteSuccess"
            type="success"
            message="Object deleted successfully!"
        />
        <template #footer>
            <div
                :class="{
                    'safari-repaint-fix': true,
                    'run-fix': isDeleting
                }"
            >
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
            </div>
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
        deleteError: {type: [String, Object], default: null},
        deleteSuccess: {type: Boolean, default: false},
        usagesUrl: {type: String, default: ''}
    },
    computed: {
        deleteErrorMessage() {
            let err = this.parseErrorMessage;
            if (!err) {
                return null;
            }
            if (err.includes('Object is in use')) {
                err += ` &mdash; <a
                    href="${this.usagesUrl}?object=${this.deletingObject.id}"
                    target="_blank"
                    rel="noopener noreferrer"
                    style="text-decoration: underline;"
                    class="check-usages-button"
                >Check for usages</a>`;
            }

            return err;
        },
        parseErrorMessage() {
            const err = this.deleteError;
            if (!err) {
                return null;
            }
            if (typeof err === 'string') {
                return err;
            }
            // Support common API error shapes
            if (typeof err === 'object') {
                if (typeof err.error === 'string' && err.error) {
                    return err.error;
                }
                if (typeof err.message === 'string' && err.message) {
                    return err.message;
                }
                // As a last resort, provide a generic message
                try {
                    return JSON.stringify(err);
                } catch (e) {
                    return 'An unknown error occurred';
                }
            }

            return String(err);
        }
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
