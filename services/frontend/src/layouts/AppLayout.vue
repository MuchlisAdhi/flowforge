<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSSE } from '@/composables/useSSE'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const { connected, connect } = useSSE()

onMounted(() => {
  connect()
})

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}

const navItems = [
  { name: 'Dashboard', path: '/', icon: '📊' },
  { name: 'Workflows', path: '/workflows', icon: '⚡' },
  { name: 'Runs', path: '/runs', icon: '▶️' },
  { name: 'Health', path: '/health', icon: '💚' },
  { name: 'AI Builder', path: '/ai-builder', icon: '🤖', roles: ['admin', 'editor'] },
]
</script>

<template>
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col">
      <div class="p-4 border-b border-gray-700">
        <h1 class="text-xl font-bold">⚡ FlowForge</h1>
        <p class="text-xs text-gray-400 mt-1">Workflow Orchestration</p>
      </div>

      <nav class="flex-1 p-4 space-y-1">
        <RouterLink
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          v-show="!item.roles || (auth.user && item.roles.includes(auth.user.role))"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
          active-class="!bg-primary-600 !text-white"
        >
          <span>{{ item.icon }}</span>
          <span>{{ item.name }}</span>
        </RouterLink>
      </nav>

      <!-- User info -->
      <div class="p-4 border-t border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-400">{{ auth.user?.role }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span
              class="w-2 h-2 rounded-full"
              :class="connected ? 'bg-green-400' : 'bg-red-400'"
              :title="connected ? 'Connected' : 'Disconnected'"
            ></span>
            <button
              @click="handleLogout"
              class="text-gray-400 hover:text-white text-sm"
              title="Logout"
            >
              ↗️
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 overflow-auto">
      <div class="p-6">
        <slot />
      </div>
    </main>
  </div>
</template>
