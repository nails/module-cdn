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

</style> 
