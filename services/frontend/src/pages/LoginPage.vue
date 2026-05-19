<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('admin@flowforge.local')
const password = ref('password')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Invalid email or password. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex">
    <!-- Left panel: Login form -->
    <div class="flex-1 flex items-center justify-center px-8">
      <div class="w-full max-w-sm">
        <!-- Logo -->
        <div class="mb-10">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
              <span class="text-white text-lg font-bold">FF</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">FlowForge</h1>
          </div>
          <p class="text-sm text-gray-500">Sign in to your workflow orchestration platform</p>
        </div>

        <!-- Error -->
        <Transition name="fade">
          <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ error }}
          </div>
        </Transition>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="space-y-5">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              class="input"
              placeholder="you@company.com"
              required
              autocomplete="email"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
            <input
              id="password"
              v-model="password"
              type="password"
              class="input"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            />
          </div>

          <button type="submit" class="btn-primary w-full btn-lg" :disabled="loading">
            <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ loading ? 'Signing in...' : 'Sign in' }}
          </button>
        </form>

        <!-- Demo credentials -->
        <div class="mt-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
          <p class="text-xs font-medium text-gray-500 mb-2">Demo Credentials</p>
          <div class="space-y-1 text-xs text-gray-600 font-mono">
            <p>Admin: admin@flowforge.local</p>
            <p>Editor: editor@flowforge.local</p>
            <p>Viewer: viewer@flowforge.local</p>
            <p class="text-gray-400">Password: password</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right panel: Gradient background -->
    <div class="hidden lg:flex lg:flex-1 bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 items-center justify-center p-12">
      <div class="max-w-md text-white">
        <h2 class="text-3xl font-bold mb-4">Orchestrate with confidence</h2>
        <p class="text-indigo-200 text-lg leading-relaxed">
          Define, execute, and monitor automated workflows with real-time DAG visualization, intelligent retry logic, and AI-powered workflow generation.
        </p>
        <div class="mt-8 grid grid-cols-2 gap-4">
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="text-2xl font-bold">DAG</p>
            <p class="text-xs text-indigo-200">Visual workflow engine</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="text-2xl font-bold">SSE</p>
            <p class="text-xs text-indigo-200">Real-time monitoring</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="text-2xl font-bold">RBAC</p>
            <p class="text-xs text-indigo-200">Multi-tenant security</p>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-lg p-4">
            <p class="text-2xl font-bold">AI</p>
            <p class="text-xs text-indigo-200">Natural language builder</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
