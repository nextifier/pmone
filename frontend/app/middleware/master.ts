export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated, user } = useSanctumAuth<User>();

  if (!isAuthenticated.value) {
    return navigateTo("/login");
  }

  // Check if user has master or admin role
  const hasRequiredRole = user.value?.roles?.some((role: string) => ["master"].includes(role));

  if (!hasRequiredRole) {
    return createError({
      statusCode: 403,
      statusMessage: "Forbidden: Only master users can access this page",
      fatal: true,
    });
  }
});
