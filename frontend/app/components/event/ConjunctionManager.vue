<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title flex items-center justify-between">
        <span>Conjunction events</span>
        <Button type="button" variant="outline" size="sm" @click="addDialogOpen = true">
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          <span>Add</span>
        </Button>
      </div>
    </div>
    <div class="frame-panel">
      <div v-if="loading" class="flex justify-center py-6">
        <Spinner class="size-5" />
      </div>

      <div v-else-if="!conjunctions?.length" class="text-muted-foreground py-6 text-center text-sm tracking-tight">
        No conjunction events yet. Add events that happen at the same time and venue.
      </div>

      <div v-else ref="listContainer" class="space-y-2">
        <div
          v-for="item in conjunctions"
          :key="item.id"
          :data-item-id="item.id"
          class="bg-muted/50 flex items-center gap-x-3 rounded-lg border px-3 py-2.5"
        >
          <Icon
            name="lucide:grip-vertical"
            class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
          />

          <div
            class="bg-muted border-border aspect-4/5 w-10 shrink-0 overflow-hidden rounded-md border"
          >
            <img
              v-if="item.poster_image?.sm"
              :src="item.poster_image.sm"
              :alt="item.title"
              class="size-full object-cover select-none"
              loading="lazy"
            />
          </div>

          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium tracking-tight">{{ item.title }}</p>
            <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
              {{ eventSubtitle(item) }}
            </p>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="iconSm"
            v-tippy="'Remove conjunction'"
            aria-label="Remove conjunction"
            :disabled="removeLoading === item.id"
            class="text-muted-foreground hover:text-destructive shrink-0"
            @click="handleRemove(item.id)"
          >
            <Spinner v-if="removeLoading === item.id" class="size-4" />
            <Icon v-else name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>

      <!-- Cross-event scan & redeem (tickets) -->
      <div
        v-if="conjunctions?.length"
        class="mt-3 flex items-start justify-between gap-3 border-t pt-3"
      >
        <div class="min-w-0 space-y-1">
          <Label for="conjunction-cross-scan" class="cursor-pointer text-sm font-medium tracking-tight">
            Allow cross-event scan & redeem
          </Label>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            When on, a ticket from any event in this in-conjunction set can be scanned and redeemed
            at the others, sharing one entry gate.
          </p>
        </div>
        <Switch
          id="conjunction-cross-scan"
          :model-value="allowCrossScan"
          :disabled="crossScanSaving"
          @update:model-value="handleCrossScanToggle"
        />
      </div>
    </div>
  </div>

  <!-- Add Conjunction Dialog -->
  <DialogResponsive v-model:open="addDialogOpen">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <h3 class="text-lg font-semibold tracking-tighter">Add conjunction event</h3>
        <p class="text-muted-foreground mt-1 text-sm tracking-tight">
          Select an event that runs at the same time and venue. It will be linked in both directions.
        </p>

        <div v-if="availableLoading" class="mt-4 flex justify-center py-6">
          <Spinner class="size-5" />
        </div>

        <div v-else-if="!availableEvents?.length" class="mt-4 py-4 text-center">
          <p class="text-muted-foreground text-sm tracking-tight">No available events to add.</p>
        </div>

        <div v-else class="mt-4 flex max-h-64 flex-col gap-y-1 overflow-y-auto">
          <button
            v-for="ev in availableEvents"
            :key="ev.id"
            type="button"
            @click="handleAdd(ev.id)"
            :disabled="addLoading"
            class="hover:bg-muted flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-left transition active:scale-98"
          >
            <div
              class="bg-muted border-border aspect-4/5 w-10 shrink-0 overflow-hidden rounded-md border"
            >
              <img
                v-if="ev.poster_image?.sm"
                :src="ev.poster_image.sm"
                :alt="ev.title"
                class="size-full object-cover select-none"
                loading="lazy"
              />
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium tracking-tight">{{ ev.title }}</p>
              <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                {{ eventSubtitle(ev) }}
              </p>
            </div>
          </button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { useSortableList } from "@/composables/useSortableList";
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
});

const route = useRoute();
const client = useSanctumClient();

const conjunctions = ref([]);
const loading = ref(true);
const removeLoading = ref(null);
const addDialogOpen = ref(false);
const addLoading = ref(false);
const availableEvents = ref([]);
const availableLoading = ref(false);
const allowCrossScan = ref(false);
const crossScanSaving = ref(false);

const apiBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}/conjunctions`,
);

function eventSubtitle(event) {
  return [event.date_label, event.location].filter(Boolean).join(" · ");
}

async function fetchConjunctions() {
  loading.value = true;
  try {
    const res = await client(apiBase.value);
    conjunctions.value = res.data;
    allowCrossScan.value = !!res.allow_cross_scan;
  } catch {
    toast.error("Failed to load conjunction events");
  } finally {
    loading.value = false;
  }
}

async function handleCrossScanToggle(next) {
  if (crossScanSaving.value) return;
  const previous = allowCrossScan.value;
  allowCrossScan.value = next;
  crossScanSaving.value = true;
  try {
    await client(`${apiBase.value}/cross-scan`, {
      method: "PUT",
      body: { allow_cross_scan: next },
    });
    toast.success(next ? "Cross-event scan enabled" : "Cross-event scan disabled");
  } catch (error) {
    allowCrossScan.value = previous;
    toast.error(error.response?._data?.message || "Failed to update cross-event scan");
  } finally {
    crossScanSaving.value = false;
  }
}

// --- Drag-drop reorder (useSortableList mutates `conjunctions` in place) ---
const listContainer = ref(null);

useSortableList(listContainer, conjunctions, {
  onReorder: async () => {
    const order = conjunctions.value.map((c) => c.id);
    try {
      await client(`${apiBase.value}/reorder`, { method: "POST", body: { order } });
      conjunctions.value.forEach((c, idx) => (c.order_column = idx + 1));
    } catch {
      toast.error("Failed to reorder conjunction events");
      await fetchConjunctions();
    }
  },
});

async function fetchAvailable() {
  availableLoading.value = true;
  try {
    const res = await client(`${apiBase.value}/available`);
    availableEvents.value = res.data;
  } catch {
    toast.error("Failed to load available events");
  } finally {
    availableLoading.value = false;
  }
}

watch(addDialogOpen, (open) => {
  if (open) fetchAvailable();
});

async function handleAdd(conjunctionEventId) {
  addLoading.value = true;
  try {
    await client(apiBase.value, {
      method: "POST",
      body: { conjunction_event_id: conjunctionEventId },
    });
    toast.success("Conjunction event added");
    addDialogOpen.value = false;
    await fetchConjunctions();
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to add conjunction");
  } finally {
    addLoading.value = false;
  }
}

async function handleRemove(conjunctionEventId) {
  removeLoading.value = conjunctionEventId;
  try {
    await client(`${apiBase.value}/${conjunctionEventId}`, {
      method: "DELETE",
    });
    toast.success("Conjunction event removed");
    conjunctions.value = conjunctions.value.filter((c) => c.id !== conjunctionEventId);
  } catch {
    toast.error("Failed to remove conjunction");
  } finally {
    removeLoading.value = null;
  }
}

onMounted(() => {
  fetchConjunctions();
});
</script>
