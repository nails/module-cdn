<template>
  <BaseModal
    :visible="visible"
    title="Create New Bucket"
    @close="handleClose"
  >
    <div class="form-group">
      <label for="bucket-name">Bucket Name</label>
      <input
        type="text"
        id="bucket-name"
        v-model="localBucketName"
        placeholder="Enter bucket name"
        :disabled="isCreatingBucket"
        @keyup.enter="handleCreate"
      />
    </div>
    <div v-if="createBucketError" class="error-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      {{ createBucketError }}
    </div>
    <div v-if="createBucketSuccess" class="success-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      Bucket created successfully!
    </div>
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
  </BaseModal>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';

export default {
  name: 'CreateBucketModal',
  components: { ModalBase, Button },
  props: {
    visible: { type: Boolean, default: false },
    newBucketName: { type: String, default: '' },
    isCreatingBucket: { type: Boolean, default: false },
    createBucketError: { type: String, default: null },
    createBucketSuccess: { type: Boolean, default: false }
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
.form-group {
  margin-bottom: 1rem;
}
.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}
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
.error-message {
  background: #fef2f2;
  border: 1px solid #fee2e2;
  color: #b91c1c;
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.error-message svg {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}
.success-message {
  background: #ecfdf5;
  border: 1px solid #d1fae5;
  color: #047857;
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.success-message svg {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}
</style> 