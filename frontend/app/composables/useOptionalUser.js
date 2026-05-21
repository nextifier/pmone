/**
 * Returns the currently authenticated user, when an auth system is available.
 *
 * In the pmone admin app this is backed by Sanctum (`useSanctumAuth`), so the
 * hotel booking flow can prefill guest details from the logged-in user.
 *
 * The pmone-events monorepo ships a no-op variant of this composable (public
 * event sites have no login), which keeps the booking pages/components
 * byte-identical across both repositories.
 */
export const useOptionalUser = () => {
  const { user } = useSanctumAuth();
  return { user };
};
