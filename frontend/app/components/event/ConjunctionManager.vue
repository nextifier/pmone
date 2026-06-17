<template>
  <div class="frame">
    <div class="frame-header">
      <div class="frame-title flex items-center justify-between">
        <span>Conjunction Events</span>
        <button
          type="button"
          @click="addDialogOpen = true"
          class="border-border hover:bg-muted text-foreground flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          <span>Add</span>
        </button>
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
            <span class="text-sm font-medium tracking-tight">{{ item.title }}</span>
            <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
              {{ eventSubtitle(item) }}
            </p>
          </div>

          <button
            type="button"
            @click="handleRemove(item.id)"
            :disabled="removeLoading === item.id"
            class="text-muted-foreground hover:text-destructive rounded p-1 transition"
          >
            <Spinner v-if="removeLoading === item.id" class="size-3.5" />
            <Icon v-else name="lucide:trash-2" class="size-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Conjunction Dialog -->
  <DialogResponsive v-model:open="addDialogOpen">
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="text-primary text-lg font-semibold tracking-tight">Add Conjunction Event</div>
        <p class="text-body mt-1.5 text-sm tracking-tight">
          Select an event that runs at the same time and venue. This will be added bidirectionally.
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
  } catch {
    toast.error("Failed to load conjunction events");
  } finally {
    loading.value = false;
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
