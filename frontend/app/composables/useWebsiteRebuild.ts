import { toast } from "vue-sonner";

interface UseWebsiteRebuildOptions {
  /** Worker name -> the name a person recognises. */
  displayName: (worker: string) => string;
  /** Called once the jobs are queued, after Cloudflare has had a moment to catch up. */
  onQueued?: () => void;
  /** Called immediately on success, e.g. to clear a table selection. */
  onSuccess?: () => void;
}

/**
 * The confirmation-and-queue machinery behind every Rebuild button.
 *
 * A rebuild spends real build minutes and can queue twenty jobs at once, so it
 * is worth a beat of confirmation — and there must be exactly ONE confirmation
 * to keep correct. The row action, the bulk pill, the header button and the
 * detail page all funnel through this.
 *
 * `pending` doubles as the open state: null means no dialog, an array means one
 * is waiting on an answer.
 */
export function useWebsiteRebuild(options: UseWebsiteRebuildOptions) {
  const client = useSanctumClient();

  const pending = ref<string[] | null>(null);
  const rebuilding = ref(false);

  const open = computed({
    get: () => pending.value !== null,
    set: (value: boolean) => {
      if (!value) pending.value = null;
    },
  });

  const pendingNames = computed(() => (pending.value || []).map(options.displayName));

  function requestRebuild(workers: string[] | string) {
    const unique = [...new Set(Array.isArray(workers) ? workers : [workers])].filter(Boolean);
    if (!unique.length) return;
    pending.value = unique;
  }

  async function confirmRebuild() {
    const workers = pending.value || [];
    if (!workers.length) return;

    rebuilding.value = true;

    try {
      const result = await client("/api/websites/rebuild", {
        method: "POST",
        body: { workers },
      });

      toast.success(result.message);
      options.onSuccess?.();

      // Cloudflare needs a moment before the new build shows up in its API.
      setTimeout(() => options.onQueued?.(), 3000);
    } catch (e: any) {
      toast.error(e?.data?.message || "Could not queue the rebuild.");
    } finally {
      rebuilding.value = false;
      pending.value = null;
    }
  }

  return { pending, pendingNames, rebuilding, open, requestRebuild, confirmRebuild };
}
