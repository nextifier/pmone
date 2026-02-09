/**
 * Permission & Authorization Composable
 *
 * A comprehensive composable for checking user roles and permissions throughout the application.
 * Uses Spatie Laravel Permission format on the backend.
 *
 * @example
 * ```typescript
 * const { hasRole, hasPermission, isAdmin, canEditPost } = usePermission();
 *
 * if (hasRole('admin')) { ... }
 * if (hasPermission('users.create')) { ... }
 * if (isAdmin.value) { ... }
 * if (canEditPost(post)) { ... }
 * ```
 */
export const usePermission = () => {
  const { user } = useSanctumAuth<User>();

  // ============================================
  // Role Checking Functions
  // ============================================

  /**
   * Check if user has a specific role
   * @param role - Role name to check (e.g., 'admin', 'master')
   */
  const hasRole = (role: string): boolean => {
    if (!user.value?.roles) return false;
    return user.value.roles.includes(role);
  };

  /**
   * Check if user has ANY of the specified roles
   * @param roles - Array of role names
   */
  const hasAnyRole = (roles: string[]): boolean => {
    if (!user.value?.roles || roles.length === 0) return false;
    return roles.some((role) => user.value.roles.includes(role));
  };

  /**
   * Check if user has ALL of the specified roles
   * @param roles - Array of role names
   */
  const hasAllRoles = (roles: string[]): boolean => {
    if (!user.value?.roles || roles.length === 0) return false;
    return roles.every((role) => user.value.roles.includes(role));
  };

  // ============================================
  // Permission Checking Functions
  // ============================================

  /**
   * Check if user has a specific permission
   * @param permission - Permission name to check (e.g., 'users.create', 'posts.delete')
   */
  const hasPermission = (permission: string): boolean => {
    if (!user.value?.permissions) return false;
    return user.value.permissions.includes(permission);
  };

  /**
   * Check if user has ANY of the specified permissions
   * @param permissions - Array of permission names
   */
  const hasAnyPermission = (permissions: string[]): boolean => {
    if (!user.value?.permissions || permissions.length === 0) return false;
    return permissions.some((permission) => user.value.permissions.includes(permission));
  };

  /**
   * Check if user has ALL of the specified permissions
   * @param permissions - Array of permission names
   */
  const hasAllPermissions = (permissions: string[]): boolean => {
    if (!user.value?.permissions || permissions.length === 0) return false;
    return permissions.every((permission) => user.value.permissions.includes(permission));
  };

  // ============================================
  // Convenient Computed Properties for Roles
  // ============================================

  const isMaster = computed(() => hasRole("master"));
  const isAdmin = computed(() => hasRole("admin"));
  const isStaff = computed(() => hasRole("staff"));
  const isWriter = computed(() => hasRole("writer"));
  const isUser = computed(() => hasRole("user"));

  const isAdminOrMaster = computed(() => hasAnyRole(["admin", "master"]));
  const isStaffOrAbove = computed(() => hasAnyRole(["staff", "admin", "master"]));

  // ============================================
  // Resource-Specific Permission Helpers
  // ============================================

  /**
   * Check if user can edit a specific post
   * Rules: User with posts.update permission can edit any post, or user must be the creator
   */
  const canEditPost = (post: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("posts.update")) return true;
    return post.created_by === user.value.id;
  };

  /**
   * Check if user can delete a specific post
   * Rules: User with posts.delete permission can delete any post, or user must be the creator
   */
  const canDeletePost = (post: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("posts.delete")) return true;
    return post.created_by === user.value.id;
  };

  /**
   * Check if user can edit a specific project
   * Rules: User with projects.update permission can edit any project, or user must be the creator
   */
  const canEditProject = (project: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("projects.update")) return true;
    return project.created_by === user.value.id;
  };

  /**
   * Check if user can delete a specific project
   * Rules: User with projects.delete permission can delete any project, or user must be the creator
   */
  const canDeleteProject = (project: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("projects.delete")) return true;
    return project.created_by === user.value.id;
  };

  /**
   * Check if user can edit a specific task
   * Rules: User with tasks.update permission can edit any task, or user must be the creator
   */
  const canEditTask = (task: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("tasks.update")) return true;
    return task.created_by === user.value.id;
  };

  /**
   * Check if user can delete a specific task
   * Rules: User with tasks.delete permission can delete any task, or user must be the creator
   */
  const canDeleteTask = (task: { created_by: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("tasks.delete")) return true;
    return task.created_by === user.value.id;
  };

  /**
   * Check if user can edit a specific user
   * Rules: User with users.update permission can edit any user, or user must be editing themselves
   */
  const canEditUser = (targetUser: { id: number }): boolean => {
    if (!user.value) return false;
    if (hasPermission("users.update")) return true;
    return targetUser.id === user.value.id;
  };

  /**
   * Check if user can delete a specific user
   * Rules: Only users with users.delete permission can delete users (cannot delete yourself)
   */
  const canDeleteUser = (targetUser: { id: number }): boolean => {
    if (!user.value) return false;
    if (!hasPermission("users.delete")) return false;
    return targetUser.id !== user.value.id; // Cannot delete yourself
  };

  /**
   * Generic ownership check
   * @param resource - Any resource with created_by field
   */
  const isOwner = (resource: { created_by: number }): boolean => {
    if (!user.value) return false;
    return resource.created_by === user.value.id;
  };

  /**
   * Check if user can perform action on resource
   * Rules: Check ownership - for permission-based access, use specific permission checks
   */
  const canManageResource = (resource: { created_by: number }): boolean => {
    if (!user.value) return false;
    return isOwner(resource);
  };

  return {
    // Role checking
    hasRole,
    hasAnyRole,
    hasAllRoles,

    // Permission checking
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    // Computed role checks
    isMaster,
    isAdmin,
    isStaff,
    isWriter,
    isUser,
    isAdminOrMaster,
    isStaffOrAbove,

    // Resource-specific permissions
    canEditPost,
    canDeletePost,
    canEditProject,
    canDeleteProject,
    canEditTask,
    canDeleteTask,
    canEditUser,
    canDeleteUser,
    isOwner,
    canManageResource,

    // Direct user access (for custom checks)
    user,
  };
};
