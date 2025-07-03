<template>
    <ModalBase
        :visible="visible"
        title="Create New Bucket"
        @close="handleClose"
    >
        <form-group
            label="Bucket Name"
            for="bucket-name"
        >
            <input
                type="text"
                id="bucket-name"
                v-model="localBucketName"
                placeholder="Enter bucket name"
                :disabled="isCreatingBucket"
                @keyup.enter="handleCreate"
            />
        </form-group>
        <Status
            v-if="createBucketError"
            type="error"
            :message="createBucketError"
        />
        <Status
            v-if="createBucketSuccess"
            type="success"
            message="Bucket created successfully!"
        />
        <template #footer>
            <Button
                variant="secondary"
                text="Cancel"
                icon="cancel"
                @click="handleClose"
                :disabled="isCreatingBucket"
            />
            <Button
                variant="primary"
                :text="isCreatingBucket ? 'Creating...' : 'Create Bucket'"
                icon="add"
                @click="handleCreate"
                :disabled="!localBucketName || isCreatingBucket"
                :loading="isCreatingBucket"
            />
        </template>
    </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import Status from "../Status.vue";
import FormGroup from "../Form/Group.vue";

export default {
    name: 'ModalCreateBucket',
    components: {FormGroup, Status, ModalBase, Button},
    props: {
        visible: {type: Boolean, default: false},
        newBucketName: {type: String, default: ''},
        isCreatingBucket: {type: Boolean, default: false},
        createBucketError: {type: String, default: null},
        createBucketSuccess: {type: Boolean, default: false}
    },
    data() {
        return {
            localBucketName: this.newBucketName
        };
    },
    watch: {
        newBucketName(newVal) {
            this.localBucketName = newVal;
        }
    },
    methods: {
        handleClose() {
            this.$emit('close');
        },
        handleCreate() {
            this.$emit('create', this.localBucketName);
        }
    }
};
</script>

<style scoped>
input[type="text"] {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.875rem;
    color: #111827;
}

input[type="text"]:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
}

input[type="text"]:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}
</style> 
