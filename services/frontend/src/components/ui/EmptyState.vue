<script setup lang="ts">
defineProps<{
  icon?: string
  title: string
  description?: string
}>()
</script>

<template>
  <div class="text-center py-12">
    <div v-if="icon" class="text-4xl mb-3">{{ icon }}</div>
    <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
    <p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p>
    <div class="mt-4">
      <slot />
    </div>
  </div>
</template>
