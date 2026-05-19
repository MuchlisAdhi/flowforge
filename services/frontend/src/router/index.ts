import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/pages/DashboardPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/workflows',
      name: 'workflows',
      component: () => import('@/pages/WorkflowListPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/workflows/new',
      name: 'workflow-create',
      component: () => import('@/pages/WorkflowEditorPage.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'editor'] },
    },
    {
      path: '/workflows/:id',
      name: 'workflow-detail',
      component: () => import('@/pages/WorkflowDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/workflows/:id/edit',
      name: 'workflow-edit',
      component: () => import('@/pages/WorkflowEditorPage.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'editor'] },
    },
    {
      path: '/runs',
      name: 'runs',
      component: () => import('@/pages/RunHistoryPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/runs/:id',
      name: 'run-detail',
      component: () => import('@/pages/RunDetailPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/health',
      name: 'health',
      component: () => import('@/pages/HealthDashboardPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/ai-builder',
      name: 'ai-builder',
      component: () => import('@/pages/AIWorkflowBuilderPage.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'editor'] },
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (!to.meta.requiresAuth && auth.isAuthenticated && to.name === 'login') {
    return { name: 'dashboard' }
  }

  // Role-based guard
  const requiredRoles = to.meta.roles as string[] | undefined
  if (requiredRoles && auth.user && !requiredRoles.includes(auth.user.role)) {
    return { name: 'dashboard' }
  }
})

export default router
