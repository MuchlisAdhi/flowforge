<script setup lang="ts">
defineProps<{
  status: string
  size?: 'sm' | 'md'
  pulse?: boolean
}>()

const statusConfig: Record<string, { class: string; label: string }> = {
  success: { class: 'badge-success', label: 'Success' },
  failed: { class: 'badge-danger', label: 'Failed' },
  timeout: { class: 'badge-danger', label: 'Timeout' },
  running: { class: 'badge-info', label: 'Running' },
  pending: { class: 'badge-warning', label: 'Pending' },
  cancelled: { class: 'badge-gray', label: 'Cancelled' },
  skipped: { class: 'badge-gray', label: 'Skipped' },
  active: { class: 'badge-success', label: 'Active' },
  inactive: { class: 'badge-gray', label: 'Inactive' },
}
</script>

<template>
  <span :class="[statusConfig[status]?.class || 'badge-gray', size === 'sm' ? 'text-[10px] px-2 py-0.5' : '']">
    <span
      v-if="pulse && status === 'running'"
      class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5 pulse-dot"
    ></span>
    {{ statusConfig[status]?.label || status }}
  </span>
</template>
