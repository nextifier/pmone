<template>
  <div class="frame">
    <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
      <Icon name="hugeicons:calendar-03" class="mt-0.5 size-5 shrink-0" />
      <div class="min-w-0 flex-1 space-y-1">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-base font-semibold tracking-tight">Event Days</h3>
          <Button
            v-if="hasDateRange"
            variant="outline"
            size="sm"
            :disabled="syncing"
            @click="onSync"
          >
            <Spinner v-if="syncing" class="size-4 shrink-0" />
            <Icon v-else name="hugeicons:calendar-setting-02" class="size-4 shrink-0" />
            <span>{{ days.length ? "Sync from event dates" : "Generate from event dates" }}</span>
          </Button>
        </div>
        <p class="text-muted-foreground text-sm tracking-tight">
          Days are derived from the event's start and end dates. Toggle which days are available for
          tickets and scanning.
        </p>
      </div>
    </div>

    <div class="frame-panel">
      <div v-if="loading" class="flex gap-2">
        <Skeleton v-for="i in 3" :key="i" class="h-9 w-24 rounded-md" />
      </div>

      <div v-else-if="!days.length" class="space-y-1 py-1 text-sm tracking-tight">
        <p class="text-muted-foreground">
          No event days yet.
          <template v-if="hasDateRange">
            Use "Generate from event dates" above to create them.
          </template>
          <template v-else>
            Set the event start and end dates first, then generate the days here.
          </template>
        </p>
      </div>

      <div v-else class="space-y-2.5">
        <div>
          <ToggleGroup
            type="multiple"
            variant="pill"
            :model-value="activeIds"
            @update:model-value="onToggle"
          >
            <ToggleGroupItem v-for="day in days" :key="day.id" :value="day.id">
              <span class="font-medium tracking-tight">{{ dayName(day) }}</span>
              <span class="text-muted-foreground text-xs tracking-tight">{{ dayDate(day) }}</span>
            </ToggleGroupItem>
          </ToggleGroup>
        </div>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          {{ activeIds.length }} of {{ days.length }} day{{ days.length === 1 ? "" : "s" }} active.
          Inactive days stay saved so tickets that reference them keep working, but are hidden from new
          tickets and scanning.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { toast } from "vue-sonner";

const props = defineProps({
  event: { type: Object, required: true },
});

const client = useSanctumClient();
const { $dayjs } = useNuxtApp();

const days = ref([]);
const loading = ref(true);
const syncing = ref(false);

const baseUrl = computed(() => `/api/events/${props.event?.id}/event-days`);
const hasDateRange = computed(() => !!props.event?.start_date && !!props.event?.end_date);
const activeIds = computed(() => days.value.filter((d) => d.is_active).map((d) => d.id));

function dayName(day) {
  const label = day.label && (day.label.en || Object.values(day.label)[0]);
  return (label && String(label).trim()) || `Day ${day.day_number}`;
}

function dayDate(day) {
  return day.date ? $dayjs(day.date).format("D MMM") : "";
}

async function load() {
  loading.value = true;
  try {
    const res = await client(baseUrl.value);
    days.value = res?.data || [];
  } catch {
    days.value = [];
  } finally {
    loading.value = false;
  }
}

async function onSync() {
  syncing.value = true;
  try {
    const res = await client(`${baseUrl.value}/sync`, { method: "POST" });
    days.value = res?.data || [];
    toast.success("Event days synced from the event dates");
  } catch (e) {
    toast.error("Failed to sync event days", { description: e?.data?.message });
  } finally {
    syncing.value = false;
  }
}

async function onToggle(next) {
  const ids = Array.isArray(next) ? next : [];
  const previous = days.value.map((d) => ({ ...d }));
  days.value = days.value.map((d) => ({ ...d, is_active: ids.includes(d.id) }));
  try {
    const res = await client(`${baseUrl.value}/active`, {
      method: "POST",
      body: { active_ids: ids },
    });
    days.value = res?.data || days.value;
  } catch (e) {
    days.value = previous;
    toast.error("Failed to update active days", { description: e?.data?.message });
  }
}

onMounted(load);
</script>
