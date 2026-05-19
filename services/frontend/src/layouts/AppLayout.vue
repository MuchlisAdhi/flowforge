<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSSE } from '@/composables/useSSE'
import { onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { connected, connect } = useSSE()

onMounted(() => {
  connect()
})

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}

const navItems = [
  { name: 'Dashboard', path: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Workflows', path: '/workflows', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { name: 'Runs', path: '/runs', icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z' },
  { name: 'Health', path: '/health', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'AI Builder', path: '/ai-builder', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', roles: ['admin', 'editor'] },
]

const isActiveRoute = (path: string) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

const userInitial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() || '?')
</script>

<template>
  <div class="min-h-screen flex bg-gray-50">
    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed inset-y-0 left-0 z-30">
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
            <span class="text-white text-sm font-bold">FF</span>
          </div>
          <div>
            <h1 class="text-sm font-bold text-gray-900">FlowForge</h1>
            <p class="text-[10px] text-gray-400 leading-none">Orchestration Engine</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <RouterLink
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          v-show="!item.roles || (auth.user && item.roles.includes(auth.user.role))"
          :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
            isActiveRoute(item.path)
              ? 'bg-indigo-50 text-indigo-700'
              : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
          ]"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span>{{ item.name }}</span>
        </RouterLink>
      </nav>

      <!-- User footer -->
      <div class="px-3 py-4 border-t border-gray-100">
        <div class="flex items-center gap-3 px-3">
          <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
            <span class="text-sm font-semibold text-indigo-700">{{ userInitial }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ auth.user?.name }}</p>
            <div class="flex items-center gap-1.5">
              <span class="badge-purple text-[9px] px-1.5 py-0">{{ auth.user?.role }}</span>
              <span
                :class="['w-1.5 h-1.5 rounded-full', connected ? 'bg-emerald-500' : 'bg-red-400']"
                :title="connected ? 'Real-time connected' : 'Disconnected'"
              ></span>
            </div>
          </div>
          <button
            @click="handleLogout"
            class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100"
            title="Sign out"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 ml-64 min-h-screen">
      <div class="max-w-7xl mx-auto px-6 py-8">
        <slot />
      </div>
    </main>
  </div>
</template>
