interface AiUsageData {
  total_credits: number;
  used_credits: number;
  remaining_credits: number;
  total_input_tokens: number;
  total_output_tokens: number;
}

export function useAiUsage() {
  const client = useSanctumClient();
  const loaded = useState("ai-usage-loaded", () => false);
  const usage = useState<AiUsageData | null>("ai-usage", () => null);

  const remainingPercent = computed(() => {
    if (!usage.value || !usage.value.total_credits) return 0;
    return Math.round(
      (usage.value.remaining_credits / usage.value.total_credits) * 100
    );
  });

  async function fetchUsage() {
    try {
      usage.value = await client("/api/ai/usage");
    } catch {
      usage.value = null;
    } finally {
      loaded.value = true;
    }
  }

  return {
    loaded,
    usage,
    remainingPercent,
    fetchUsage,
  };
}
