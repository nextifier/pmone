<script setup>
import { Spinner } from "@/components/ui/spinner";

const props = defineProps({
  guestId: { type: [Number, String], required: true },
  apiBase: { type: String, required: true },
});

const client = useSanctumClient();
const { t, d } = useI18n();

const loading = ref(true);
const items = ref([]);
const errorMessage = ref("");

const fetchActivities = async () => {
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await client(`${props.apiBase}/${props.guestId}/activities`);
    items.value = response.data ?? [];
  } catch (err) {
    errorMessage.value = err?.data?.message || err?.message || t("guests.failedToLoad");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchActivities);

const eventLabel = (event) => {
  const map = {
    created: t("guests.created"),
    updated: t("guests.updated"),
    deleted: t("guests.deleted"),
    restored: t("guests.restored"),
  };
  return map[event] || event;
};

const eventColor = (event) => {
  const map = {
    created: "bg-success/15 text-success",
    updated: "bg-info/15 text-info",
    deleted: "bg-destructive/15 text-destructive",
    restored: "bg-warning/15 text-warning",
  };
  return map[event] || "bg-muted text-muted-foreground";
};

const formatDate = (iso) => {
  if (!iso) return "";
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
};
</script>

<template>
  <div class="space-y-3">
    <div v-if="loading" class="flex justify-center py-8">
      <Spinner class="size-5" />
    </div>

    <p v-else-if="errorMessage" class="text-destructive text-sm tracking-tight">
      {{ errorMessage }}
    </p>

    <p
      v-else-if="!items.length"
      class="text-muted-foreground py-6 text-center text-sm tracking-tight"
    >
      {{ $t("guests.noActivity") }}
    </p>

    <ol v-else class="space-y-3">
      <li
        v-for="entry in items"
        :key="entry.id"
        class="border-border flex gap-3 rounded-lg border p-3"
      >
        <div class="flex shrink-0 flex-col items-center">
          <div
            class="bg-muted flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-medium"
          >
            <img
              v-if="entry.causer?.avatar"
              :src="entry.causer.avatar"
              :alt="entry.causer?.name"
              class="size-full object-cover"
            />
            <span v-else>{{ (entry.causer?.name?.[0] || "?").toUpperCase() }}</span>
          </div>
        </div>

        <div class="min-w-0 flex-1 space-y-1">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-sm font-medium tracking-tight">
              {{ entry.causer?.name || $t("guests.system") }}
            </span>
            <span
              :class="[
                'rounded-full px-2 py-0.5 text-xs tracking-tight',
                eventColor(entry.event),
              ]"
            >
              {{ eventLabel(entry.event) }}
            </span>
            <span class="text-muted-foreground ml-auto text-xs tracking-tight tabular-nums">
              {{ formatDate(entry.created_at) }}
            </span>
          </div>

          <p
            v-if="entry.description && entry.description !== entry.event"
            class="text-muted-foreground text-xs tracking-tight"
          >
            {{ entry.description }}
          </p>

          <dl
            v-if="entry.changes?.attributes"
            class="text-muted-foreground space-y-0.5 text-xs tracking-tight"
          >
            <div
              v-for="(value, key) in entry.changes.attributes"
              :key="key"
              class="flex flex-wrap gap-x-1.5"
            >
              <dt class="font-medium">{{ key }}:</dt>
              <dd class="break-all">{{ String(value) }}</dd>
            </div>
          </dl>
        </div>
      </li>
    </ol>
  </div>
</template>
