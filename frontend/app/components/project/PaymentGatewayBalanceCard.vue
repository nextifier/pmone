<template>
  <div class="bg-muted/40 border-border rounded-lg border p-3 sm:p-4">
    <div class="flex items-center justify-between gap-2">
      <span class="text-muted-foreground text-sm tracking-tight">Account balance</span>
      <button
        type="button"
        v-tippy="'Refresh balance'"
        class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1 transition disabled:opacity-50"
        :disabled="loading"
        @click="fetchBalance(true)"
      >
        <Icon name="hugeicons:refresh" class="size-3.5" :class="loading ? 'animate-spin' : ''" />
      </button>
    </div>

    <div class="mt-1.5">
      <Skeleton v-if="loading && !balance" class="h-7 w-36" />

      <div v-else-if="error" class="flex items-start gap-x-1.5">
        <Icon name="hugeicons:alert-circle" class="text-destructive mt-0.5 size-4 shrink-0" />
        <p class="text-destructive text-sm tracking-tight">{{ error }}</p>
      </div>

      <div v-else-if="balance">
        <p class="text-lg font-semibold tracking-tighter">
          {{ formatPrice(balance.available) }}
        </p>
        <div
          class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span v-for="account in secondaryAccounts" :key="account.account_type">
            {{ account.account_type }}: {{ formatPrice(account.balance) }}
          </span>
          <span v-if="secondaryAccounts.length" aria-hidden="true">·</span>
          <span>Updated {{ formatRelativeTime(balance.fetched_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  projectUsername: { type: String, required: true },
  gatewayId: { type: [Number, String], required: true },
});

const client = useSanctumClient();
const { formatPrice, formatRelativeTime } = useFormatters();

const loading = ref(false);
const balance = ref(null);
const error = ref(null);

// `available` already reflects the CASH account; only surface the rest here.
const secondaryAccounts = computed(() =>
  (balance.value?.accounts || []).filter((a) => a.account_type !== "CASH")
);

async function fetchBalance(refresh = false) {
  loading.value = true;
  error.value = null;
  try {
    const res = await client(
      `/api/projects/${props.projectUsername}/payment-gateways/${props.gatewayId}/balance`,
      { query: refresh ? { refresh: 1 } : {} }
    );
    balance.value = res.data;
  } catch (e) {
    error.value = e?.data?.message || "Failed to load balance.";
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchBalance());
</script>
