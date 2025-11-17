<template>
  <div class="border-border bg-card group hover:bg-muted/50 rounded-lg border p-4 transition-all">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <div class="mb-3 flex items-center gap-2">
          <Icon :name="getSourceIcon(source.source)" class="text-primary size-5 shrink-0" />
          <h3 class="text-foreground truncate font-semibold">{{ source.source || "Unknown" }}</h3>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="text-muted-foreground rounded-md bg-gray-500/10 px-2 py-0.5 text-xs font-medium capitalize"
          >
            {{ source.medium || "Unknown" }}
          </span>
        </div>
      </div>
      <div class="text-right">
        <p class="text-foreground text-2xl font-semibold">
          {{ formatNumber(source.sessions || 0) }}
        </p>
        <p class="text-muted-foreground text-xs">sessions</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  source: {
    type: Object,
    required: true,
  },
});

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const getSourceIcon = (sourceName) => {
  const source = sourceName?.toLowerCase() || "";
  if (source.includes("google")) return "hugeicons:google";
  if (source.includes("facebook") || source.includes("fb")) return "hugeicons:facebook-01";
  if (source.includes("twitter") || source.includes("x.com")) return "hugeicons:new-twitter";
  if (source.includes("instagram")) return "hugeicons:instagram";
  if (source.includes("linkedin")) return "hugeicons:linkedin-01";
  if (source.includes("youtube")) return "hugeicons:youtube";
  if (source.includes("direct") || source === "(direct)") return "hugeicons:cursor-pointer-02";
  if (source.includes("email") || source.includes("mail")) return "hugeicons:mail-01";
  if (source.includes("referral")) return "hugeicons:link-square-02";
  return "hugeicons:globe-02";
};
</script>
