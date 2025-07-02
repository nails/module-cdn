<template>
  <ModalBase
    :visible="visible"
    title="Copy URL"
    @close="handleClose"
  >
    <template v-if="urlToCopy">
      <div class="form-group">
        <label>Source URL</label>
        <div class="url-input-group">
          <textarea
            v-model="urlToCopy.src"
            readonly
            rows="2"
            ref="srcTextarea"
          ></textarea>
          <button class="copy-button" @click="copyUrl('src')" title="Copy source URL">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
        </div>
        <div class="url-description">Use this URL to display the file in a webpage or embed it in content. If accessed directly the browser will attempt to render it in-window (works well for images and PDFs)</div>
      </div>
      <div class="form-group">
        <label>Download URL</label>
        <div class="url-input-group">
          <textarea
            v-model="urlToCopy.download"
            readonly
            rows="2"
            ref="downloadTextarea"
          ></textarea>
          <button class="copy-button" @click="copyUrl('download')" title="Copy download URL">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          </button>
        </div>
        <div class="url-description">Use this URL to force the browser to download the file instead of displaying it. The file will download to the user's device as "{{ urlToCopy.humanName || 'human-name.ext' }}"</div>
      </div>
    </template>
    <template v-else>
      <div class="form-group">No file selected for URL copy.</div>
    </template>
    <Status
      v-if="urlCopyError"
      type="error"
      :message="urlCopyError"
    />
    <Status
      v-if="urlCopySuccess"
      type="success"
      message="URL copied to clipboard!"
    />
    <template #footer>
      <Button 
        variant="secondary" 
        text="Cancel" 
        icon="cancel" 
        @click="handleClose" 
      />
    </template>
  </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import Status from '../Status.vue';

export default {
  name: 'ModalUrlCopy',
  components: { ModalBase, Button, Status },
  props: {
    visible: { type: Boolean, default: false },
    urlToCopy: { type: Object, default: null },
    urlCopyError: { type: String, default: null },
    urlCopySuccess: { type: Boolean, default: false }
  },
  methods: {
    async copyUrl(type) {
      try {
        await navigator.clipboard.writeText(this.urlToCopy[type]);
        this.$emit('copy-success');
      } catch (err) {
        this.$emit('copy-error', 'Failed to copy URL. Please try selecting and copying manually.');
      }
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
.url-input-group {
  position: relative;
  margin-bottom: 0.5rem;
}
.url-input-group textarea {
  width: 100%;
  padding: 0.5rem;
  padding-right: 2.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 0.875rem;
  color: #111827;
  resize: none;
  background: #f9fafb;
}
.url-input-group textarea:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
}
.copy-button {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  background: none;
  border: none;
  padding: 0.25rem;
  cursor: pointer;
  color: #6b7280;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.copy-button:hover {
  background: #e5e7eb;
  color: #4b5563;
}
.copy-button svg {
  width: 1rem;
  height: 1rem;
}
.url-description {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 1rem;
}
</style> 
