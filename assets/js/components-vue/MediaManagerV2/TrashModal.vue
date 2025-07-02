<template>
  <BaseModal
    :visible="visible"
    title="Trash"
    @close="handleClose"
  >
    <div v-if="loadingTrashedItems" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading trashed items...</p>
    </div>
    <div v-else-if="trashedItems.length === 0" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="empty-icon">
        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      <p>No items in trash</p>
    </div>
    <div v-else class="trashed-items-list">
      <div class="trashed-items-header">
        <div class="select-all-container">
          <label class="select-all-label">
            <input 
              type="checkbox" 
              :checked="selectedTrashedItems.length === trashedItems.length" 
              @change="toggleSelectAll"
            />
            <span>Select All</span>
          </label>
        </div>
        <div class="selection-info" v-if="selectedTrashedItems.length > 0">
          {{ selectedTrashedItems.length }} item{{ selectedTrashedItems.length > 1 ? 's' : '' }} selected
        </div>
      </div>
      <div class="trashed-items-container">
        <FilePreview
          v-for="item in trashedItems" 
          :key="item.id" 
          :file="item"
          :show-checkbox="true"
          :is-selected="selectedTrashedItems.includes(item.id)"
          :container-class="'clickable'"
          :show-border="false"
          @click="toggleTrashedItem(item.id)"
          @selection-change="() => toggleTrashedItem(item.id)"
        />
      </div>
    </div>
    <div v-if="restoreError" class="error-message modal-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      {{ restoreError }}
    </div>
    <div v-if="restoreSuccess" class="success-message modal-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      Items restored successfully!
    </div>
    <template #footer>
      <Button 
        variant="secondary" 
        text="Cancel" 
        icon="cancel" 
        @click="handleClose" 
        :disabled="isRestoring"
      />
      <Button 
        variant="primary" 
        :text="isRestoring ? 'Restoring...' : 'Restore Selected'" 
        icon="restore" 
        @click="handleRestore"
        :disabled="selectedTrashedItems.length === 0 || isRestoring"
        :loading="isRestoring"
      />
    </template>
  </BaseModal>
</template>

<script>
import BaseModal from './BaseModal.vue';
import Button from './Button.vue';
import FilePreview from './FilePreview.vue';

export default {
  name: 'TrashModal',
  components: { BaseModal, Button, FilePreview },
  props: {
    visible: { type: Boolean, default: false },
    trashedItems: { type: Array, default: () => [] },
    selectedTrashedItems: { type: Array, default: () => [] },
    loadingTrashedItems: { type: Boolean, default: false },
    isRestoring: { type: Boolean, default: false },
    restoreError: { type: String, default: null },
    restoreSuccess: { type: Boolean, default: false }
  },
  methods: {
    handleClose() {
      this.$emit('close');
    },
    handleRestore() {
      this.$emit('restore');
    },
    toggleTrashedItem(itemId) {
      this.$emit('toggle-selection', itemId);
    },
    toggleSelectAll() {
      this.$emit('toggle-select-all');
    }
  }
};
</script>

<style scoped>
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;
}
.loading-spinner {
  margin-bottom: 1rem;
  display: inline-block;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 2px solid rgba(79, 70, 229, 0.3);
  border-top-color: #4f46e5;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;
  color: #6b7280;
}
.empty-icon {
  width: 3rem;
  height: 3rem;
  color: #9ca3af;
  margin-bottom: 1rem;
}
.trashed-items-list {
  display: flex;
  flex-direction: column;
}
.trashed-items-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-top-left-radius: 6px;
  border-top-right-radius: 6px;
}
.select-all-container {
  display: flex;
  align-items: center;
}
.select-all-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #4b5563;
  cursor: pointer;
}
.select-all-label input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
}
.selection-info {
  font-size: 0.75rem;
  color: #6b7280;
  background: #e5e7eb;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
}
.trashed-items-container {
  border: 1px solid #e5e7eb;
  border-top: none;
  border-bottom-left-radius: 6px;
  border-bottom-right-radius: 6px;
  max-height: 400px;
  overflow-y: auto;
  background: #ffffff;
}
/* Table-like styling for FilePreview components */
.trashed-items-container :deep(.file-preview) {
  border-bottom: 1px solid #e5e7eb;
  border-radius: 0;
  margin: 0;
  padding: 0.75rem 1rem;
  transition: background-color 0.15s ease;
}
.trashed-items-container :deep(.file-preview:last-child) {
  border-bottom: none;
}
.trashed-items-container :deep(.file-preview:nth-child(even)) {
  background-color: #f9fafb;
}
.trashed-items-container :deep(.file-preview:nth-child(odd)) {
  background-color: #ffffff;
}
.trashed-items-container :deep(.file-preview:hover) {
  background-color: #f3f4f6 !important;
  border-color: #d1d5db;
}
.trashed-items-container :deep(.file-preview.selected) {
  background-color: #eef2ff !important;
  border-left: 3px solid #4f46e5;
}
.trashed-items-container :deep(.file-preview.selected:nth-child(even)) {
  background-color: #eef2ff !important;
}
.error-message.modal-message,
.success-message.modal-message {
  margin-top: 16px;
  margin-bottom: 1rem;
  padding: 12px 16px;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  border-left: 4px solid;
  animation: messageSlideIn 0.3s ease forwards;
  font-size: 14px;
}
.success-message {
  background-color: rgba(220, 252, 231, 0.6);
  color: #15803d;
  border-color: #22c55e;
}
.success-message svg {
  color: #22c55e;
  height: 1.25rem;
  width: 1.25rem;
  margin-right: 0.5rem;
}
.error-message {
  background-color: rgba(254, 226, 226, 0.6);
  color: #b91c1c;
  border-color: #ef4444;
}
.error-message svg {
  color: #ef4444;
  height: 1.25rem;
  width: 1.25rem;
  margin-right: 0.5rem;
}
</style> 