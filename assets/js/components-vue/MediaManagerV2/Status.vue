<template>
  <div
    v-if="visible && (title || message || body || $slots.default)"
    :class="[
      'status-message',
      type,
      { 'with-icon': showIcon }
    ]"
    role="alert"
    :aria-live="type === 'error' ? 'assertive' : 'polite'"
  >
    <span v-if="showIcon" class="status-icon" aria-hidden="true">
      <svg v-if="type === 'success'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
      </svg>
      <svg v-else-if="type === 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
      </svg>
      <svg v-else-if="type === 'warning'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
    </span>
    <span class="status-content">
      <span v-if="title" class="status-title">{{ title }}</span>
      <span v-if="body" class="status-body" v-html="body"></span>
      <span v-else-if="message" class="status-body" v-html="message"></span>
      <span v-else-if="$slots.default">
        <slot />
      </span>
    </span>
    <button v-if="dismissible" class="status-close" @click="$emit('close')" aria-label="Dismiss">&times;</button>
  </div>
</template>

<script>
export default {
  name: 'Status',
  props: {
    type: {
      type: String,
      default: 'success', // 'success', 'error', 'warning'
      validator: v => ['success', 'error', 'warning'].includes(v)
    },
    title: {
      type: String,
      default: ''
    },
    body: {
      type: String,
      default: ''
    },
    message: {
      type: String,
      default: ''
    },
    visible: {
      type: Boolean,
      default: true
    },
    showIcon: {
      type: Boolean,
      default: true
    },
    dismissible: {
      type: Boolean,
      default: false
    }
  }
};
</script>

<style scoped>
.status-message {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  margin: 1rem 0 0 0;
  position: relative;
  animation: messageSlideIn 0.3s ease forwards;
}
.status-message.with-icon .status-icon {
  flex-shrink: 0;
  width: 1.25rem;
  height: 1.25rem;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  margin-top: 0.15em;
}
.status-icon svg {
  width: 1.25rem;
  height: 1.25rem;
  display: block;
  color: inherit;
  fill: currentColor;
}
.status-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.status-title {
  font-weight: bold;
  display: block;
  margin-bottom: 0.15em;
}
.status-body {
  display: block;
  /* Allow HTML formatting */
}
.status-close {
  background: none;
  border: none;
  color: inherit;
  font-size: 1.25rem;
  cursor: pointer;
  margin-left: 0.5rem;
  line-height: 1;
}
.status-message.success {
  background: #ecfdf5;
  border: 1px solid #d1fae5;
  color: #047857;
}
.status-message.error {
  background: #fef2f2;
  border: 1px solid #fee2e2;
  color: #b91c1c;
}
.status-message.warning {
  background: #fffbeb;
  border: 1px solid #fef3c7;
  color: #d97706;
}
@keyframes messageSlideIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style> 