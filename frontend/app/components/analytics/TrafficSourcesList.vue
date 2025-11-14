<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-foreground flex items-center gap-2 text-lg font-semibold tracking-tighter">
          <Icon name="hugeicons:link-square-02" class="size-5" />
          Traffic Sources
        </h2>
        <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
          Where your visitors come from
        </p>
      </div>
    </div>

    <div v-if="sources && sources.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="(source, index) in displayedSources"
        :key="index"
        class="border-border bg-card hover:border-foreground/20 group overflow-hidden rounded-lg border transition-all"
      >
        <div class="flex flex-col gap-3 p-4">
          <!-- Header -->
          <div class="flex items-start gap-3">
            <div
              class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
            >
              <Icon :name="getSourceIcon(source)" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="text-foreground truncate font-semibold tracking-tight">
                {{ formatSourceName(source.source || source.sessionSource) }}
              </h3>
              <p class="text-muted-foreground truncate text-xs">
                {{ source.medium || source.sessionMedium || "Unknown Medium" }}
              </p>
            </div>
          </div>

          <!-- Metrics -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-0.5">
              <p class="text-muted-foreground text-xs font-medium">Sessions</p>
              <p class="text-foreground text-xl font-bold tabular-nums">
                {{ formatNumber(source.sessions) }}
              </p>
            </div>
            <div class="space-y-0.5">
              <p class="text-muted-foreground text-xs font-medium">Users</p>
              <p class="text-foreground text-xl font-bold tabular-nums">
                {{ formatNumber(source.users || source.activeUsers || 0) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Show More/Less Button -->
      <button
        v-if="sources.length > limit"
        @click="toggleShowAll"
        class="hover:bg-muted border-border sm:col-span-2 lg:col-span-3 mx-auto flex items-center gap-x-1.5 rounded-md border px-4 py-2 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon
          :name="showAll ? 'hugeicons:arrow-up-01' : 'hugeicons:arrow-down-01'"
          class="size-4"
        />
        <span>{{ showAll ? "Show Less" : `Show All (${sources.length})` }}</span>
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-muted/30 flex flex-col items-center justify-center rounded-lg border p-8 text-center"
    >
      <Icon name="hugeicons:link-broken-02" class="text-muted-foreground size-12" />
      <p class="text-muted-foreground mt-3 text-sm">No traffic sources data available</p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  sources: {
    type: Array,
    default: () => [],
  },
  limit: {
    type: Number,
    default: 12,
  },
});

const showAll = ref(false);

const displayedSources = computed(() => {
  if (!props.sources) return [];
  return showAll.value ? props.sources : props.sources.slice(0, props.limit);
});

const toggleShowAll = () => {
  showAll.value = !showAll.value;
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatSourceName = (source) => {
  if (!source) return "Direct";
  if (source === "(direct)") return "Direct";
  if (source === "(not set)") return "Not Set";
  return source;
};

const getSourceIcon = (source) => {
  const sourceName = (source.source || source.sessionSource || "").toLowerCase();

  if (sourceName.includes("google")) return "hugeicons:google";
  if (sourceName.includes("facebook") || sourceName.includes("fb")) return "hugeicons:facebook-01";
  if (sourceName.includes("twitter") || sourceName.includes("x.com")) return "hugeicons:new-twitter";
  if (sourceName.includes("instagram")) return "hugeicons:instagram";
  if (sourceName.includes("linkedin")) return "hugeicons:linkedin-01";
  if (sourceName.includes("youtube")) return "hugeicons:youtube";
  if (sourceName.includes("tiktok")) return "hugeicons:tiktok";
  if (sourceName.includes("pinterest")) return "hugeicons:pinterest";
  if (sourceName.includes("reddit")) return "hugeicons:reddit";
  if (sourceName.includes("direct") || sourceName === "(direct)") return "hugeicons:cursor-pointer-02";
  if (sourceName.includes("email") || sourceName.includes("mail")) return "hugeicons:mail-01";
  if (sourceName.includes("referral")) return "hugeicons:link-square-02";
  if (sourceName.includes("search") || sourceName.includes("organic")) return "hugeicons:search-01";

  return "hugeicons:globe-02";
};
</script>
