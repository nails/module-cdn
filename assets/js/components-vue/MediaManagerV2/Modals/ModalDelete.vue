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
        class="mb-2"
      />
    </template>
    <template v-else>
      <div class="form-group">No file selected for deletion.</div>
    </template>
    <Status
      v-if="deleteError"
      type="error"
      :message="deleteError"
      class="mb-2"
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
      class="mb-2"
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
  components: { ModalBase: ModalBase, Button, FilePreview, Status },
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
