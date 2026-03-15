interface JobProgress {
  status: "pending" | "processing" | "completed" | "failed";
  total: number;
  processed: number;
  percentage: number;
  message: string | null;
  error_message: string | null;
  [key: string]: any;
}

interface StartJobOptions {
  method?: string;
  body?: Record<string, any>;
}

export function useJobProgress() {
  const client = useSanctumClient();

  const progress = ref<JobProgress | null>(null);
  const processing = ref(false);
  const jobId = ref<string | null>(null);

  let pollInterval: ReturnType<typeof setInterval> | null = null;
  let abortController: AbortController | null = null;
  let timeoutTimer: ReturnType<typeof setTimeout> | null = null;

  async function startJob(url: string, options?: StartJobOptions) {
    processing.value = true;
    progress.value = {
      status: "pending",
      total: 0,
      processed: 0,
      percentage: 0,
      message: null,
      error_message: null,
    };

    const response = await client(url, {
      method: options?.method || "POST",
      body: options?.body,
    });

    jobId.value = response.job_id;
    startPolling();

    // Safety net: 10 minute client-side timeout
    timeoutTimer = setTimeout(() => {
      stopPolling();
      processing.value = false;
      if (progress.value && progress.value.status !== "completed") {
        progress.value = {
          ...progress.value,
          status: "failed",
          error_message: "Operation timed out. Please check the results.",
        };
      }
    }, 10 * 60 * 1000);
  }

  function startPolling() {
    stopPolling();

    pollInterval = setInterval(async () => {
      if (!jobId.value) return;

      // Abort previous request if still pending
      if (abortController) {
        abortController.abort();
      }
      abortController = new AbortController();

      try {
        const data = await client(`/api/jobs/${jobId.value}/progress`, {
          signal: abortController.signal,
        });

        progress.value = data;

        if (data.status === "completed" || data.status === "failed") {
          stopPolling();
          processing.value = false;
        }
      } catch (err: any) {
        if (err.name === "AbortError") return;
        console.error("Failed to fetch job progress:", err);
      }
    }, 500);
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
    processing.value = false;
    jobId.value = null;
    progress.value = null;
  }

  onUnmounted(() => {
    stopPolling();
  });

  return {
    progress: readonly(progress),
    processing: readonly(processing),
    jobId: readonly(jobId),
    startJob,
    reset,
  };
}
