<template>
  <div class="bg-card border-border -mx-1 rounded-2xl border px-2.5 py-4">
    <span class="text-muted-foreground text-sm tracking-tight">Credits</span>

    <div v-if="!loaded" class="space-y-3 py-1.5">
      <Skeleton class="h-8 w-full" />
      <Skeleton class="h-4 w-full" />
    </div>

    <div v-else-if="usage" class="space-y-3 py-1.5">
      <!-- Remaining balance -->
      <div class="space-y-1.5">
        <div class="flex items-baseline justify-between">
          <span class="text-2xl tracking-tighter">${{ formatMoney(usage.remaining_credits) }}</span>
          <span class="text-muted-foreground text-sm tracking-tight">
            of ${{ formatMoney(usage.total_credits) }}
          </span>
        </div>
        <Progress :model-value="remainingPercent" class="h-1.5" />
      </div>

      <!-- Spent -->
      <div class="flex items-center justify-between gap-x-2">
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">Spent</span>
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          ${{ formatMoney(usage.used_credits) }} ·
          {{ formatTokens(usage.total_input_tokens + usage.total_output_tokens) }} tokens
        </span>
      </div>
    </div>

    <p v-else class="text-muted-foreground px-3 py-2 text-sm tracking-tight">
      Unable to load usage data.
    </p>
  </div>
</template>

<script setup lang="ts">
const { loaded, usage, remainingPercent, fetchUsage } = useAiUsage();

function formatMoney(n: number): string {
  return n.toFixed(2);
}

function formatTokens(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
  if (n >= 1000) return `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}K`;
  return String(n);
}

onMounted(() => {
  fetchUsage();
});
</script>
