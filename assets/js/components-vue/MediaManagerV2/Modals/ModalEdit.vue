<template>
  <ModalBase
    :visible="visible"
    title="Edit File"
    @close="handleClose"
  >
    <template v-if="localEditingObject">
      <form-group
        label="Filename"
        for="filename_display"
      >
        <input
          type="text"
          id="filename_display"
          v-model="localEditingObject.filename_display"
          placeholder="Enter display name"
          :disabled="isEditing"
        />
      </form-group>
      <form-group
        label="Metadata"
        sub-label="Add any custom information about this file, this will be searchable"
      >
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
      </form-group>
      <Status
        v-if="editError"
        type="error"
        :message="editError"
      />
      <Status
        v-if="editSuccess"
        type="success"
        message="Object updated successfully!"
      />
    </template>
    <template v-else>
      <div class="form-group">No file selected for editing.</div>
    </template>
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
  </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import FilePreview from '../FilePreview.vue';
import Status from '../Status.vue';
import FormGroup from '../Form/Group.vue';

export default {
  name: 'ModalEdit',
  components: { ModalBase, Button, FilePreview, Status, FormGroup },
  props: {
    visible: { type: Boolean, default: false },
    editingObject: { type: Object, default: null },
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
</style> 
