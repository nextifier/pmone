export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated, user } = useSanctumAuth<User>()

  if (!isAuthenticated.value) {
    return navigateTo('/login')
  }

  // Check if user has staff, master, or admin role
  const hasRequiredRole = user.value?.roles?.some((role: string) =>
    ['staff', 'master', 'admin'].includes(role)
  )

  if (!hasRequiredRole) {
    return createError({
      statusCode: 403,
      statusMessage: 'Forbidden: Only staff, admin, and master users can access this page',
      fatal: true
    })
  }
})
