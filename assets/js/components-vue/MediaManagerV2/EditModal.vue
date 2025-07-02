<template>
  <BaseModal
    :visible="visible"
    title="Edit File"
    @close="handleClose"
  >
    <div class="form-group">
      <label for="filename_display">Filename</label>
      <input
        type="text"
        id="filename_display"
        v-model="localEditingObject.filename_display"
        placeholder="Enter display name"
        :disabled="isEditing"
      />
    </div>
    <div class="form-group">
      <label>Metadata</label>
      <div class="form-sub-label">Add any custom information about this file, this will be searchable</div>
      <div class="metadata-editor">
        <div class="metadata-list">
          <div v-for="(item, index) in localEditingObject.metadata" :key="index" class="metadata-item">
            <input
              type="text"
              v-model="item.key"
              placeholder="Key"
              :disabled="isEditing"
              class="metadata-key"
            />
            <input
              type="text"
              v-model="item.value"
              placeholder="Value"
              :disabled="isEditing"
              class="metadata-value"
            />
            <button
              class="remove-button"
              @click="removeMetadata(index)"
              :disabled="isEditing"
              type="button"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
        <button
          class="add-button"
          @click="addMetadata"
          :disabled="isEditing"
          type="button"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
          </svg>
          Add Metadata
        </button>
      </div>
    </div>
    <div v-if="editError" class="error-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      {{ editError }}
    </div>
    <div v-if="editSuccess" class="success-message">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      Object updated successfully!
    </div>
    <template #footer>
      <Button 
        variant="secondary" 
        text="Cancel" 
        icon="cancel" 
        @click="handleClose" 
        :disabled="isEditing"
      />
      <Button 
        variant="primary" 
        :text="isEditing ? 'Saving...' : 'Save Changes'" 
        icon="save" 
        @click="handleSave"
        :disabled="!localEditingObject?.filename_display || isEditing"
        :loading="isEditing"
      />
    </template>
  </BaseModal>
</template>

<script>
import BaseModal from './BaseModal.vue';
import Button from './Button.vue';

export default {
  name: 'EditModal',
  components: { BaseModal, Button },
  props: {
    visible: { type: Boolean, default: false },
    editingObject: { type: Object, required: true },
    isEditing: { type: Boolean, default: false },
    editError: { type: String, default: null },
    editSuccess: { type: Boolean, default: false }
  },
  data() {
    return {
      localEditingObject: this.cloneEditingObject(this.editingObject)
    };
  },
  watch: {
    editingObject: {
      handler(newVal) {
        this.localEditingObject = this.cloneEditingObject(newVal);
      },
      deep: true
    }
  },
  methods: {
    cloneEditingObject(obj) {
      // Deep clone and ensure metadata is always an array
      const clone = obj ? JSON.parse(JSON.stringify(obj)) : { metadata: [] };
      if (!Array.isArray(clone.metadata)) clone.metadata = [];
      return clone;
    },
    addMetadata() {
      if (!this.localEditingObject.metadata) {
        this.localEditingObject.metadata = [];
      }
      this.localEditingObject.metadata.push({ key: '', value: '' });
    },
    removeMetadata(index) {
      this.localEditingObject.metadata.splice(index, 1);
    },
    handleSave() {
      this.$emit('save', { ...this.localEditingObject });
    },
    handleClose() {
      this.$emit('close');
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
.form-sub-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
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
.metadata-editor {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px;
  margin-top: 4px;
}
.metadata-item {
  display: flex;
  gap: 6px;
  align-items: center;
}
.metadata-key,
.metadata-value {
  flex: 1;
  padding: 4px 8px;
  margin-bottom: 4px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-size: 13px;
  background: white;
  transition: all 0.2s ease;
  height: 28px;
}
.metadata-key:focus,
.metadata-value:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
}
.metadata-key:disabled,
.metadata-value:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}
.remove-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  margin-bottom: 4px;
  border: none;
  background: none;
  color: #6b7280;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease;
  flex-shrink: 0;
}
.remove-button svg {
  width: 14px;
  height: 14px;
}
.remove-button:hover {
  background: #fee2e2;
  color: #b91c1c;
}
.remove-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.add-button {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  background: white;
  color: #4b5563;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.add-button svg {
  width: 16px;
  height: 16px;
}
.add-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}
.add-button:disabled {
  opacity: 0.5;
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