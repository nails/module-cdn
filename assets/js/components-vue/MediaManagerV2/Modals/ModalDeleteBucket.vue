<template>
  <ModalBase
    :visible="visible"
    title="Delete Bucket"
    @close="handleClose"
  >
    <template v-if="bucketToDelete">
      <Status
        type="warning"
        :title="'Are you sure you want to delete this bucket?'"
        :message="'The following bucket will be deleted:<br><strong>' + bucketToDelete.label + ' (ID: ' + bucketToDelete.id + ')</strong><br>This action cannot be undone.'"
        class="mb-2"
      />
    </template>
    <template v-else>
      <div class="form-group">No bucket selected for deletion.</div>
    </template>
    <Status
      v-if="deleteBucketError"
      type="error"
      :message="deleteBucketError"
      class="mb-2"
    />
    <Status
      v-if="deleteBucketSuccess"
      type="success"
      message="Bucket deleted successfully!"
      class="mb-2"
    />
    <template #footer>
      <Button 
        variant="secondary" 
        text="Cancel" 
        icon="cancel" 
        @click="handleClose" 
        :disabled="isDeletingBucket"
      />
      <Button 
        variant="danger" 
        :text="isDeletingBucket ? 'Deleting...' : 'Delete Bucket'" 
        icon="delete" 
        @click="handleConfirm" 
        :disabled="isDeletingBucket"
        :loading="isDeletingBucket"
      />
    </template>
  </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import Status from '../Status.vue';

export default {
  name: 'ModalDeleteBucket',
  components: { ModalBase, Button, Status },
  props: {
    visible: { type: Boolean, default: false },
    bucketToDelete: { type: Object, default: null },
    isDeletingBucket: { type: Boolean, default: false },
    deleteBucketError: { type: String, default: null },
    deleteBucketSuccess: { type: Boolean, default: false }
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
.warning-content p {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  color: #78350f;
}
.object-details {
  color: #78350f;
  line-height: 1.5;
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
