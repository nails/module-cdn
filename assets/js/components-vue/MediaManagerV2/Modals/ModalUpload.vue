<template>
  <ModalBase
    :visible="visible"
    title="Upload Files"
    @close="handleClose"
  >
    <div
      class="upload-zone"
      :class="{ 'drag-over': dragOver }"
      @click="$refs.fileInput.click()"
      @dragover.prevent="dragOver = true"
      @dragleave.prevent="dragOver = false"
      @drop.prevent="handleFileDrop"
    >
      <div class="upload-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
      </div>
      <div class="upload-text">
        <p class="primary-text">Drag and drop files here or click to browse</p>
        <p class="secondary-text">Max file size: {{ formatFileSize(maxUploadSize) }}</p>
      </div>
      <input
        type="file"
        multiple
        class="hidden"
        @change="handleFileSelect"
        ref="fileInput"
      >
    </div>

    <div class="bucket-selector">
      <label>Select Bucket:</label>
      <bucket-selector
        v-model="selectedUploadBucket"
        :buckets="buckets"
        placeholder="Select upload bucket"
        :single-select="true"
      />
    </div>

    <div class="overall-progress" v-if="isUploading">
      <div class="overall-progress__bar">
        <div class="overall-progress__fill" :style="{ width: overallProgress + '%' }"></div>
      </div>
      <span class="overall-progress__text">{{ Math.round(overallProgress) }}% Complete</span>
    </div>

    <Status
      v-if="uploadError"
      type="error"
      :message="uploadError"
    />
    <Status
      v-if="uploadSuccess"
      type="success"
      message="Files uploaded successfully!"
    />

    <div class="file-list" v-if="filesToUpload.length > 0">
      <h4>Files to Upload ({{ filesToUpload.length }})</h4>
      <div class="file-item" v-for="(file, index) in filesToUpload" :key="index">
        <div class="file-info">
          <span class="file-name">{{ file.name }}</span>
          <span class="file-size">{{ formatFileSize(file.size) }}</span>
          <div class="progress-bar" v-if="isUploading && uploadProgress[index] !== undefined">
            <div class="progress-bar__fill" :style="{ width: uploadProgress[index] + '%' }"></div>
            <span class="progress-bar__text">{{ Math.round(uploadProgress[index]) }}%</span>
          </div>
        </div>
        <button class="remove-button" @click="removeFile(index)" v-if="!isUploading">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>

    <template #footer>
      <Button
        variant="secondary"
        text="Cancel"
        icon="cancel"
        @click="handleClose"
        :disabled="isUploading"
      />
      <Button
        variant="primary"
        :text="isUploading ? 'Uploading...' : 'Upload'"
        icon="upload"
        @click="uploadFiles"
        :disabled="filesToUpload.length === 0 || !selectedUploadBucket.length || isUploading"
        :loading="isUploading"
      />
    </template>
  </ModalBase>
</template>

<script>
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';
import BucketSelector from '../BucketSelector.vue';
import Status from '../Status.vue';

export default {
  name: 'ModalUpload',
  components: {
    ModalBase,
    Button,
    BucketSelector,
    Status
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    buckets: {
      type: Array,
      required: true
    },
    maxUploadSize: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      dragOver: false,
      filesToUpload: [],
      selectedUploadBucket: [],
      isUploading: false,
      uploadProgress: [],
      overallProgress: 0,
      uploadError: null,
      uploadSuccess: false
    };
  },
  methods: {
    handleClose() {
      this.$emit('close');
    },
    handleFileSelect(event) {
      const files = Array.from(event.target.files);
      this.filesToUpload.push(...files);
      event.target.value = '';
    },
    handleFileDrop(event) {
      this.dragOver = false;
      const files = Array.from(event.dataTransfer.files);
      this.filesToUpload.push(...files);
    },
    removeFile(index) {
      this.filesToUpload.splice(index, 1);
    },
    formatFileSize(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
    },
    async uploadFiles() {
      if (this.filesToUpload.length === 0 || !this.selectedUploadBucket.length) return;
      this.isUploading = true;
      this.uploadError = null;
      this.uploadSuccess = false;
      this.uploadProgress = Array(this.filesToUpload.length).fill(0);
      this.overallProgress = 0;
      try {
        // Simulate upload logic here, replace with actual API call
        for (let i = 0; i < this.filesToUpload.length; i++) {
          await new Promise(resolve => setTimeout(resolve, 500));
          this.uploadProgress[i] = 100;
          this.overallProgress = ((i + 1) / this.filesToUpload.length) * 100;
        }
        this.uploadSuccess = true;
        this.$emit('upload-success');
        setTimeout(() => {
          this.handleClose();
        }, 1500);
      } catch (e) {
        this.uploadError = 'Failed to upload files.';
      } finally {
        this.isUploading = false;
      }
    }
  }
};
</script>

<style scoped>
.upload-zone {
  border: 2px dashed #e5e7eb;
  border-radius: 6px;
  padding: 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #f9fafb;
}
.upload-zone.drag-over {
  border-color: #4f46e5;
  background: #eef2ff;
}
.upload-icon {
  margin-bottom: 1rem;
  svg {
    width: 3rem;
    height: 3rem;
    color: #6b7280;
    margin: 0 auto;
  }
}
.upload-text .primary-text {
  margin: 0;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
}
.upload-text .secondary-text {
  margin: 0.5rem 0 0;
  color: #6b7280;
  font-size: 0.75rem;
}
.bucket-selector {
  margin: 1.5rem 0 1rem 0;
}
.overall-progress {
  margin-bottom: 1rem;
}
.overall-progress__bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-bottom: 0.5rem;
}
.overall-progress__fill {
  height: 100%;
  background: #4f46e5;
  border-radius: 4px;
  transition: width 0.2s ease;
}
.overall-progress__text {
  font-size: 0.85rem;
  color: #4b5563;
}
.file-list {
  margin-top: 1.5rem;
}
.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid #f3f4f6;
}
.file-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.file-name {
  font-size: 0.875rem;
  color: #111827;
}
.file-size {
  font-size: 0.75rem;
  color: #6b7280;
}
.progress-bar {
  width: 120px;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-top: 0.25rem;
  position: relative;
}
.progress-bar__fill {
  height: 100%;
  background: #10b981;
  border-radius: 4px;
  transition: width 0.2s ease;
}
.progress-bar__text {
  position: absolute;
  right: 8px;
  top: -18px;
  font-size: 0.75rem;
  color: #4b5563;
}
.remove-button {
  background: none;
  border: none;
  color: #b91c1c;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: background 0.2s;
}
.remove-button:hover {
  background: #fee2e2;
}
</style> 
