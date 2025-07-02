<template>
  <BaseModal
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
      <div class="warning-message">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <div class="warning-content">
          <h4>Are you sure you want to delete this file?</h4>
        </div>
      </div>
    </template>
    <template v-else>
      <div class="form-group">No file selected for deletion.</div>
    </template>
    <div v-if="deleteError" class="error-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      {{ deleteError }}
      <a
        v-if="deleteError && deleteError.includes('Object is in use') && deletingObject"
        :href="`${siteUrl}/admin/cdn/utilities/usages?object=${deletingObject.id}`"
        target="_blank"
        class="check-usages-button"
      >
        Check for usages
      </a>
    </div>
    <div v-if="deleteSuccess" class="success-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      Object deleted successfully!
    </div>
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
  </BaseModal>
</template>

<script>
import BaseModal from './BaseModal.vue';
import Button from './Button.vue';
import FilePreview from './FilePreview.vue';

export default {
  name: 'DeleteModal',
  components: { BaseModal, Button, FilePreview },
  props: {
    visible: { type: Boolean, default: false },
    deletingObject: { type: Object, default: null },
    isDeleting: { type: Boolean, default: false },
    deleteError: { type: String, default: null },
    deleteSuccess: { type: Boolean, default: false },
    siteUrl: { type: String, default: '' }
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
.warning-message {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 6px;
  margin-bottom: 1rem;
}
.warning-message svg {
  width: 1.5rem;
  height: 1.5rem;
  color: #d97706;
  flex-shrink: 0;
}
.warning-content h4 {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #92400e;
}
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
.form-group {
  margin-bottom: 1rem;
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