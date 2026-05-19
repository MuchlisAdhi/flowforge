<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  subtitle?: string
  trend?: 'up' | 'down' | 'neutral'
  color?: 'default' | 'success' | 'danger' | 'warning' | 'info'
}>()
</script>

<template>
  <div class="card-compact">
    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ label }}</p>
    <div class="mt-2 flex items-baseline gap-2">
      <p
        :class="[
          'text-2xl font-bold tracking-tight',
          color === 'success' ? 'text-emerald-600' :
          color === 'danger' ? 'text-red-600' :
          color === 'warning' ? 'text-amber-600' :
          color === 'info' ? 'text-indigo-600' :
          'text-gray-900'
        ]"
      >
        {{ value }}
      </p>
      <span
        v-if="trend"
        :class="[
          'text-xs font-medium',
          trend === 'up' ? 'text-emerald-600' :
          trend === 'down' ? 'text-red-600' :
          'text-gray-500'
        ]"
      >
        {{ trend === 'up' ? '↑' : trend === 'down' ? '↓' : '→' }}
      </span>
    </div>
    <p v-if="subtitle" class="mt-1 text-xs text-gray-500">{{ subtitle }}</p>
  </div>
</template>
