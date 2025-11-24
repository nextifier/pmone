export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated, user } = useSanctumAuth<User>();

  if (!isAuthenticated.value) {
    return navigateTo("/login");
  }

  // Get allowed roles from route meta
  const allowedRoles = (to.meta.roles as string[]) || [];

  if (allowedRoles.length === 0) {
    console.warn("No roles specified for this route");
    return;
  }

  // Check if user has any of the required roles
  const hasRequiredRole = user.value?.roles?.some((role: string) =>
    allowedRoles.includes(role)
  );

  if (!hasRequiredRole) {
    const rolesText = allowedRoles.join(", ");
    return createError({
      statusCode: 403,
      statusMessage: `Forbidden: Only ${rolesText} users can access this page`,
      fatal: true,
    });
  }
});
