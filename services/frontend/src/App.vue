<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import { computed } from 'vue'

const auth = useAuthStore()
const isAuthenticated = computed(() => auth.isAuthenticated)
</script>

<template>
  <AppLayout v-if="isAuthenticated">
    <RouterView />
  </AppLayout>
  <RouterView v-else />
</template>
