import { toast } from "vue-sonner";

/**
 * Impersonation is master-only, enforced in code on both ends (never a grantable
 * permission), and cannot target yourself or another master.
 *
 * Shared by the row actions menu, the profile button and the last-page cell:
 * all three start it the same way, then hard-navigate so every composable
 * re-reads the impersonated identity.
 */
export function useImpersonate() {
  const { isMaster, user } = usePermission();
  const { refreshIdentity } = useSanctumAuth();
  const client = useSanctumClient();

  const pending = ref(false);

  const canImpersonate = (target) => {
    if (!isMaster.value) return false;
    if (!target?.id || target.id === user.value?.id) return false;
    return !(target.roles || []).includes("master");
  };

  /**
   * @param {{ username: string }} target
   * @param {string} [redirectTo] Internal path to land on; anything else falls
   *   back to the dashboard.
   */
  const impersonate = async (target, redirectTo = "/dashboard") => {
    if (pending.value) return;
    pending.value = true;
    try {
      await client(`/api/users/${target.username}/impersonate`, { method: "POST" });
      await refreshIdentity();
      // Same-origin paths only: "//host" is protocol-relative and would leave the app.
      const isInternalPath =
        typeof redirectTo === "string" &&
        redirectTo.startsWith("/") &&
        !redirectTo.startsWith("//");
      window.location.assign(isInternalPath ? redirectTo : "/dashboard");
    } catch (error) {
      toast.error("Failed to impersonate user", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
      pending.value = false;
    }
  };

  return { canImpersonate, impersonate, pending };
}
