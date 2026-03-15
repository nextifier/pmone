interface ImportProgress {
  status: "pending" | "processing" | "completed" | "failed";
  total_rows: number;
  processed_rows: number;
  imported_count: number;
  percentage: number;
  errors: Array<{
    row: number;
    attribute: string;
    errors: string[];
    values: Record<string, any>;
  }>;
  error_message: string | null;
}

export function useImportProgress() {
  const client = useSanctumClient();

  const progress = ref<ImportProgress | null>(null);
  const importing = ref(false);
  const importId = ref<string | null>(null);

  let pollInterval: ReturnType<typeof setInterval> | null = null;
  let abortController: AbortController | null = null;
  let timeoutTimer: ReturnType<typeof setTimeout> | null = null;

  async function startImport(url: string, body: Record<string, any>) {
    importing.value = true;
    progress.value = {
      status: "pending",
      total_rows: 0,
      processed_rows: 0,
      imported_count: 0,
      percentage: 0,
      errors: [],
      error_message: null,
    };

    const response = await client(url, {
      method: "POST",
      body,
    });

    importId.value = response.import_id;
    startPolling();

    // Safety net: 10 minute client-side timeout
    timeoutTimer = setTimeout(() => {
      stopPolling();
      importing.value = false;
      if (progress.value && progress.value.status !== "completed") {
        progress.value = {
          ...progress.value,
          status: "failed",
          error_message: "Import timed out. Please check the results.",
        };
      }
    }, 10 * 60 * 1000);
  }

  function startPolling() {
    stopPolling();

    pollInterval = setInterval(async () => {
      if (!importId.value) return;

      // Abort previous request if still pending
      if (abortController) {
        abortController.abort();
      }
      abortController = new AbortController();

      try {
        const data = await client(
          `/api/imports/${importId.value}/progress`,
          { signal: abortController.signal },
        );

        progress.value = data;

        if (data.status === "completed" || data.status === "failed") {
          stopPolling();
          importing.value = false;
        }
      } catch (err: any) {
        if (err.name === "AbortError") return;
        console.error("Failed to fetch import progress:", err);
      }
    }, 2000);
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
    if (abortController) {
      abortController.abort();
      abortController = null;
    }
    if (timeoutTimer) {
      clearTimeout(timeoutTimer);
      timeoutTimer = null;
    }
  }

  function reset() {
    stopPolling();
    importing.value = false;
    importId.value = null;
    progress.value = null;
  }

  onUnmounted(() => {
    stopPolling();
  });

  return {
    progress: readonly(progress),
    importing: readonly(importing),
    importId: readonly(importId),
    startImport,
    reset,
  };
}
