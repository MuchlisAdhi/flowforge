import { ref, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRunStore } from '@/stores/runs'
import type { SSEEvent } from '@/types'

export function useSSE() {
  const connected = ref(false)
  const eventSource = ref<EventSource | null>(null)

  function connect() {
    const auth = useAuthStore()
    if (!auth.token) return

    const baseUrl = import.meta.env.VITE_API_URL || '/api'
    const url = `${baseUrl}/sse/executions?token=${auth.token}`

    eventSource.value = new EventSource(url)

    eventSource.value.addEventListener('connected', () => {
      connected.value = true
    })

    eventSource.value.addEventListener('execution', (event) => {
      const data: SSEEvent = JSON.parse(event.data)
      handleEvent(data)
    })

    eventSource.value.onerror = () => {
      connected.value = false
      // Auto-reconnect after 5 seconds
      setTimeout(() => {
        if (!connected.value) {
          disconnect()
          connect()
        }
      }, 5000)
    }
  }

  function disconnect() {
    if (eventSource.value) {
      eventSource.value.close()
      eventSource.value = null
      connected.value = false
    }
  }

  function handleEvent(event: SSEEvent) {
    const runStore = useRunStore()

    if (event.type === 'run_status') {
      runStore.updateRunStatus(event.run_id, event.status)
    } else if (event.type === 'step_status' && event.step_id) {
      runStore.updateStepStatus(event.run_id, event.step_id, event.status)
    }
  }

  onUnmounted(() => {
    disconnect()
  })

  return { connected, connect, disconnect }
}
