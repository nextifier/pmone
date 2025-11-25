/**
 * Permission Middleware
 *
 * Restricts page access based on user permissions.
 * Works with Spatie Laravel Permission package on the backend.
 *
 * Usage in pages:
 * ```typescript
 * definePageMeta({
 *   middleware: ["sanctum:auth", "permission"],
 *   permissions: ["posts.create", "posts.update"], // User needs ANY of these
 *   layout: "app",
 * });
 * ```
 *
 * Examples:
 * - Single permission: permissions: ["users.create"]
 * - Multiple permissions (OR logic): permissions: ["posts.create", "posts.update"]
 * - User needs to have at least ONE of the listed permissions
 *
 * Common permissions:
 * - users.create, users.read, users.update, users.delete
 * - posts.create, posts.read, posts.update, posts.delete
 * - roles.create, roles.read, roles.update, roles.delete
 * - projects.create, projects.read, projects.update, projects.delete
 * - admin.view, admin.settings, admin.logs
 * - analytics.view, analytics.export
 *
 * Note: This middleware checks if user has ANY of the specified permissions (OR logic).
 * If you need ALL permissions (AND logic), consider creating a separate middleware
 * or handling the check in the component itself using usePermission().hasAllPermissions()
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated, user } = useSanctumAuth<User>();

  if (!isAuthenticated.value) {
    return navigateTo("/login");
  }

  // Get required permissions from route meta
  const requiredPermissions = (to.meta.permissions as string[]) || [];

  if (requiredPermissions.length === 0) {
    console.warn("No permissions specified for this route");
    return;
  }

  // Check if user has any of the required permissions
  const hasRequiredPermission = user.value?.permissions?.some((permission: string) =>
    requiredPermissions.includes(permission)
  );

  if (!hasRequiredPermission) {
    const permissionsText = requiredPermissions.join(", ");
    return createError({
      statusCode: 403,
      statusMessage: `Forbidden: You need one of these permissions to access this page: ${permissionsText}`,
      fatal: true,
    });
  }
});
