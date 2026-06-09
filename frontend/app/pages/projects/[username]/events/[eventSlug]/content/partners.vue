<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-y-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold tracking-tight">Partners</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          Manage partner categories and their logos for this event.
        </p>
      </div>

      <div class="flex shrink-0 gap-2">
        <button
          @click="copyDialogOpen = true"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
          <span>Copy from Event</span>
        </button>
        <button
          @click="addCategoryDialogOpen = true"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>Add Category</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!categories.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:layers-01" />
        </div>
        <div>
          <Icon name="hugeicons:link-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:star" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No partner categories yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Create categories like "Supported by", "Media Partners", etc. then add partners to each category.
        </p>
      </div>
    </div>

    <!-- Category sections (styled like the public Credits section) -->
    <div v-else ref="categoriesContainer" class="flex w-full flex-wrap gap-x-2 gap-y-8">
      <div
        v-for="category in categories"
        :key="category.id"
        class="group/cat bg-pattern-diagonal border-border relative flex grow flex-col items-center justify-center gap-y-4 rounded-xl border px-4 py-6 sm:px-6"
      >
        <!-- Floating category badge (matches Credits) -->
        <span
          class="text-primary bg-background absolute top-0 left-1/2 flex max-w-[calc(100%-1rem)] -translate-x-1/2 -translate-y-1/2 items-center gap-x-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold tracking-tighter text-nowrap"
        >
          <span class="truncate">{{ category.name }}</span>
          <span v-if="category.no_container" class="text-muted-foreground font-normal">full-width</span>
          <span class="text-muted-foreground tabular-nums font-normal">{{ category.partners?.length || 0 }}</span>
        </span>

        <!-- Management toolbar (revealed on hover; floats just above the box so it
             never overlaps the partner tiles or their top-right detach buttons) -->
        <div
          class="bg-background absolute right-3 bottom-full z-20 mb-1 flex items-center gap-0.5 rounded-lg border p-0.5 opacity-0 shadow-xs transition group-hover/cat:opacity-100 pointer-coarse:opacity-100"
        >
          <button
            type="button"
            class="category-drag-handle text-muted-foreground hover:bg-muted inline-flex size-7 cursor-grab items-center justify-center rounded-md active:cursor-grabbing"
            v-tippy="'Drag to reorder'"
          >
            <Icon name="hugeicons:drag-drop" class="size-4" />
          </button>
          <button
            type="button"
            @click="openAddPartner(category)"
            class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
            v-tippy="'Add partner'"
          >
            <Icon name="lucide:plus" class="size-4" />
          </button>
          <button
            type="button"
            @click="openEditCategory(category)"
            class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
            v-tippy="'Edit category'"
          >
            <Icon name="lucide:pencil" class="size-4" />
          </button>
          <button
            type="button"
            @click="handleDeleteCategory(category)"
            class="hover:bg-destructive/10 inline-flex size-7 items-center justify-center rounded-md"
            v-tippy="'Delete category'"
          >
            <Icon name="lucide:trash" class="text-destructive size-4" />
          </button>
        </div>

        <!-- Logos grid (matches Credits) -->
        <div
          v-if="category.partners?.length"
          :ref="(el) => setPartnerContainerRef(category.id, el)"
          class="flex w-full flex-wrap items-center justify-evenly gap-x-0 gap-y-2"
        >
          <div
            v-for="partner in category.partners"
            :key="partner.pivot_id"
            :data-pivot-id="partner.pivot_id"
            class="drag-handle group/logo relative flex cursor-grab items-center justify-center rounded-xl active:cursor-grabbing dark:hover:bg-white"
            :class="{
              'w-full max-w-48 p-3 xl:max-w-56': category.no_container,
              'aspect-3/2': !category.no_container,
              'h-20 xl:h-24': category.partners.length <= 10,
              'h-18 xl:h-20': category.partners.length > 10,
            }"
          >
            <img
              v-if="partner.partner_logo?.sm || partner.partner_logo?.original"
              :src="partner.partner_logo?.sm || partner.partner_logo?.original"
              :alt="partner.name"
              width="300"
              height="200"
              loading="lazy"
              decoding="async"
              draggable="false"
              class="pointer-events-none max-h-full w-auto max-w-full object-contain select-none dark:brightness-90 dark:contrast-200 dark:grayscale dark:invert-[75%] dark:group-hover/logo:filter-none"
            />
            <div
              v-else
              class="bg-muted text-muted-foreground pointer-events-none flex size-10 items-center justify-center rounded-lg text-sm font-medium"
            >
              {{ partner.name?.charAt(0)?.toUpperCase() }}
            </div>

            <!-- Detach -->
            <button
              type="button"
              @click.stop="handleRemovePartner(category, partner)"
              @pointerdown.stop
              class="bg-background absolute -top-2 -right-2 z-30 inline-flex size-6 items-center justify-center rounded-full border opacity-0 shadow-xs transition group-hover/logo:opacity-100 pointer-coarse:opacity-100"
              v-tippy="`Remove ${partner.name}`"
            >
              <Icon name="lucide:x" class="text-destructive size-3" />
            </button>
          </div>
        </div>

        <!-- Empty category -->
        <div v-else class="text-muted-foreground py-4 text-center text-sm tracking-tight">
          No partners yet.
          <button @click="openAddPartner(category)" class="text-primary hover:underline">
            Add one
          </button>
        </div>
      </div>
    </div>

    <!-- Add Category Dialog -->
    <DialogResponsive v-model:open="addCategoryDialogOpen" dialog-max-width="24rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editingCategory ? "Edit Category" : "Add Category" }}
          </h3>
          <form @submit.prevent="handleSaveCategory" class="mt-4 space-y-4">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="categoryForm.name" placeholder="e.g. Media Partners" auto-focus required />
            </div>
            <div class="flex items-center gap-x-2">
              <Switch v-model="categoryForm.no_container" />
              <Label class="cursor-pointer">No container (full-width logo)</Label>
            </div>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="addCategoryDialogOpen = false"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="categorySaving"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="categorySaving" class="size-4" />
                <span v-else>{{ editingCategory ? "Save" : "Create" }}</span>
              </button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Add Partner to Category Dialog -->
    <DialogResponsive v-model:open="addPartnerDialogOpen" dialog-max-width="28rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Add Partner</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Search for an existing partner or create a new one.
          </p>

          <form @submit.prevent="handleAddPartner" class="mt-4 space-y-4">
            <div class="space-y-2">
              <Label>Partner Name</Label>
              <AutocompleteRoot v-model="partnerSearchTerm" :ignore-filter="true">
                <AutocompleteAnchor as-child>
                  <AutocompleteInput
                    placeholder="Type to search partners..."
                    autocomplete="off"
                    class="placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-background border-border focus-visible:border-ring focus-visible:ring-ring flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm tracking-tight shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
                    auto-focus
                  />
                </AutocompleteAnchor>
                <AutocompletePortal>
                  <AutocompleteContent
                    position="popper"
                    :side-offset="4"
                    hide-when-empty
                    class="bg-popover text-popover-foreground z-[100] w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-md border shadow-md"
                  >
                    <AutocompleteViewport class="max-h-48 p-1">
                      <AutocompleteItem
                        v-for="p in partnerResults"
                        :key="p.id"
                        :value="p.name"
                        class="data-[highlighted]:bg-muted flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-left outline-none select-none"
                        @select="selectedPartner = p"
                      >
                        <img
                          v-if="p.partner_logo?.sm"
                          :src="p.partner_logo.sm"
                          class="size-6 rounded object-cover"
                          alt=""
                        />
                        <div
                          v-else
                          class="text-muted-foreground bg-muted flex size-6 items-center justify-center rounded text-xs font-medium"
                        >
                          {{ p.name.charAt(0).toUpperCase() }}
                        </div>
                        <span class="text-sm">{{ p.name }}</span>
                        <Icon
                          v-if="selectedPartner?.id === p.id"
                          name="lucide:check"
                          class="ml-auto size-4"
                        />
                      </AutocompleteItem>
                      <AutocompleteEmpty
                        v-if="partnerSearchTerm.trim()"
                        class="text-muted-foreground px-2 py-4 text-center text-sm"
                      >
                        No partners found. A new one will be created.
                      </AutocompleteEmpty>
                    </AutocompleteViewport>
                  </AutocompleteContent>
                </AutocompletePortal>
              </AutocompleteRoot>
            </div>

            <div v-if="!selectedPartner && partnerSearchTerm.trim()" class="space-y-2">
              <Label>Website URL</Label>
              <Input v-model="newPartnerUrl" placeholder="https://example.com" type="url" />
            </div>

            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="addPartnerDialogOpen = false"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="addPartnerSaving || (!selectedPartner && !partnerSearchTerm.trim())"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="addPartnerSaving" class="size-4" />
                <span v-else>Add</span>
              </button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Copy from Event Dialog -->
    <DialogResponsive v-model:open="copyDialogOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Copy Partners from Event</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Copy all partner categories and their partners from another event.
          </p>

          <div class="mt-4 space-y-4">
            <div v-if="eventsLoading" class="flex justify-center py-4">
              <Spinner class="size-5" />
            </div>
            <div v-else-if="!availableEvents.length" class="text-muted-foreground py-4 text-center text-sm tracking-tight">
              No other events have partners configured.
            </div>
            <div v-else class="space-y-2">
              <Label>Source Event</Label>
              <Select v-model="selectedSourceEventId">
                <SelectTrigger>
                  <SelectValue placeholder="Select an event" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="ev in availableEvents"
                    :key="ev.id"
                    :value="String(ev.id)"
                  >
                    {{ ev.title }} ({{ ev.partner_categories_count }} categories)
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="copyDialogOpen = false"
              >
                Cancel
              </button>
              <button
                @click="handleCopyFromEvent"
                :disabled="!selectedSourceEventId || copySaving"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="copySaving" class="size-4" />
                <span v-else>Copy</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Category Confirm Dialog -->
    <DialogResponsive v-model:open="deleteCategoryDialogOpen" dialog-max-width="24rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-lg font-semibold tracking-tight">Delete category?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            This will delete "{{ deletingCategory?.name }}" and remove all partner associations in this category.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteCategoryDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="confirmDeleteCategory"
              :disabled="deleteCategorySaving"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="deleteCategorySaving" class="size-4 text-white" />
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSortableList } from "@/composables/useSortableList";
import Sortable from "sortablejs";
import {
  AutocompleteAnchor,
  AutocompleteContent,
  AutocompleteEmpty,
  AutocompleteInput,
  AutocompleteItem,
  AutocompletePortal,
  AutocompleteRoot,
  AutocompleteViewport,
} from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Partners" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/partner-categories`;

// --- Data ---
const categories = ref([]);
const loading = ref(true);

const fetchCategories = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    categories.value = response.data;
  } catch (err) {
    console.error("Failed to load partner categories:", err);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchCategories);

// --- Category sortable ---
const categoriesContainer = ref(null);

useSortableList(categoriesContainer, categories, {
  onReorder: async () => {
    const order = categories.value.map((c) => c.id);
    try {
      await client(`${apiBase}/update-order`, {
        method: "POST",
        body: { order },
      });
    } catch (err) {
      toast.error("Failed to update category order");
    }
  },
  sortableOptions: { handle: ".category-drag-handle" },
});

// --- Partner sortable within categories ---
const partnerContainerRefs = {};
const partnerSortableInstances = {};

const setPartnerContainerRef = (categoryId, el) => {
  if (el) {
    partnerContainerRefs[categoryId] = el;
  }
};

const initPartnerSortables = () => {
  // Destroy existing instances
  Object.values(partnerSortableInstances).forEach((instance) => instance.destroy());
  Object.keys(partnerSortableInstances).forEach((key) => delete partnerSortableInstances[key]);

  // Create new instances per category
  for (const category of categories.value) {
    const el = partnerContainerRefs[category.id];
    if (!el || !category.partners?.length) continue;

    partnerSortableInstances[category.id] = Sortable.create(el, {
      animation: 200,
      handle: ".drag-handle",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      dragClass: "sortable-drag",
      // Touch: long-press to reorder so swiping over the logos scrolls instead of
      // accidentally dragging. Mouse stays instant (delayOnTouchOnly).
      delay: 200,
      delayOnTouchOnly: true,
      touchStartThreshold: 8,
      onStart: () => {
        if (typeof navigator !== "undefined" && navigator.vibrate) navigator.vibrate(15);
      },
      onEnd: async () => {
        // Read new order from DOM
        const items = el.querySelectorAll("[data-pivot-id]");
        const order = Array.from(items).map((item) => Number(item.dataset.pivotId));

        // Update local state
        const cat = categories.value.find((c) => c.id === category.id);
        if (cat) {
          cat.partners = order.map((pivotId) =>
            cat.partners.find((p) => p.pivot_id === pivotId)
          ).filter(Boolean);
        }

        try {
          await client(`${apiBase}/${category.slug}/partners/update-order`, {
            method: "POST",
            body: { order },
          });
        } catch (err) {
          toast.error("Failed to update partner order");
        }
      },
    });
  }
};

watch(
  () => categories.value,
  async () => {
    await nextTick();
    initPartnerSortables();
  },
  { deep: false },
);

onUnmounted(() => {
  Object.values(partnerSortableInstances).forEach((instance) => instance.destroy());
});

// --- Add/Edit Category ---
const addCategoryDialogOpen = ref(false);
const editingCategory = ref(null);
const categoryForm = reactive({ name: "", no_container: false });
const categorySaving = ref(false);

const openEditCategory = (category) => {
  editingCategory.value = category;
  categoryForm.name = category.name;
  categoryForm.no_container = category.no_container;
  addCategoryDialogOpen.value = true;
};

watch(addCategoryDialogOpen, (open) => {
  if (!open) {
    editingCategory.value = null;
    categoryForm.name = "";
    categoryForm.no_container = false;
  }
});

const handleSaveCategory = async () => {
  categorySaving.value = true;
  try {
    if (editingCategory.value) {
      await client(`${apiBase}/${editingCategory.value.slug}`, {
        method: "PUT",
        body: { name: categoryForm.name, no_container: categoryForm.no_container },
      });
      toast.success("Category updated");
    } else {
      await client(apiBase, {
        method: "POST",
        body: { name: categoryForm.name, no_container: categoryForm.no_container },
      });
      toast.success("Category created");
    }
    addCategoryDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to save category", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    categorySaving.value = false;
  }
};

// --- Delete Category ---
const deleteCategoryDialogOpen = ref(false);
const deletingCategory = ref(null);
const deleteCategorySaving = ref(false);

const handleDeleteCategory = (category) => {
  deletingCategory.value = category;
  deleteCategoryDialogOpen.value = true;
};

const confirmDeleteCategory = async () => {
  deleteCategorySaving.value = true;
  try {
    await client(`${apiBase}/${deletingCategory.value.slug}`, { method: "DELETE" });
    toast.success("Category deleted");
    deleteCategoryDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to delete category", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deleteCategorySaving.value = false;
  }
};

// --- Add Partner to Category ---
const addPartnerDialogOpen = ref(false);
const targetCategory = ref(null);
const partnerSearchTerm = ref("");
const selectedPartner = ref(null);
const newPartnerUrl = ref("");
const addPartnerSaving = ref(false);
const partnerResults = ref([]);

const openAddPartner = (category) => {
  targetCategory.value = category;
  partnerSearchTerm.value = "";
  selectedPartner.value = null;
  newPartnerUrl.value = "";
  partnerResults.value = [];
  addPartnerDialogOpen.value = true;
};

// Debounced search
let searchTimeout;
watch(partnerSearchTerm, (term) => {
  // Selecting an item from the autocomplete sets the term to the partner's name.
  // Keep the selection in that case; otherwise it would be treated as a brand-new
  // partner and create a duplicate on submit.
  if (selectedPartner.value && selectedPartner.value.name === term) {
    return;
  }
  selectedPartner.value = null;
  clearTimeout(searchTimeout);
  if (!term || term.trim().length < 1) {
    partnerResults.value = [];
    return;
  }
  searchTimeout = setTimeout(async () => {
    try {
      const response = await client(`/api/partners/search?q=${encodeURIComponent(term.trim())}`);
      partnerResults.value = response.data || [];
    } catch {
      partnerResults.value = [];
    }
  }, 300);
});

const handleAddPartner = async () => {
  if (!targetCategory.value) return;
  addPartnerSaving.value = true;
  try {
    const body = selectedPartner.value
      ? { partner_id: selectedPartner.value.id }
      : { partner_name: partnerSearchTerm.value.trim(), website_url: newPartnerUrl.value || null };

    await client(`${apiBase}/${targetCategory.value.slug}/partners`, {
      method: "POST",
      body,
    });
    toast.success("Partner added");
    addPartnerDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to add partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    addPartnerSaving.value = false;
  }
};

// --- Remove Partner ---
const handleRemovePartner = async (category, partner) => {
  try {
    await client(`${apiBase}/${category.slug}/partners/${partner.pivot_id}`, {
      method: "DELETE",
    });
    // Optimistic removal
    const cat = categories.value.find((c) => c.id === category.id);
    if (cat) {
      cat.partners = cat.partners.filter((p) => p.pivot_id !== partner.pivot_id);
    }
    toast.success("Partner removed");
  } catch (err) {
    toast.error("Failed to remove partner", {
      description: err?.data?.message || err?.message,
    });
  }
};

// --- Copy from Event ---
const copyDialogOpen = ref(false);
const availableEvents = ref([]);
const eventsLoading = ref(false);
const selectedSourceEventId = ref(null);
const copySaving = ref(false);

watch(copyDialogOpen, async (open) => {
  if (open) {
    eventsLoading.value = true;
    try {
      const response = await client("/api/events-with-partners");
      availableEvents.value = (response.data || []).filter((e) => e.id !== props.event?.id);
    } catch {
      availableEvents.value = [];
    } finally {
      eventsLoading.value = false;
    }
  }
});

const handleCopyFromEvent = async () => {
  if (!selectedSourceEventId.value) return;
  copySaving.value = true;
  try {
    const response = await client(`${apiBase}/copy-from-event`, {
      method: "POST",
      body: { source_event_id: Number(selectedSourceEventId.value) },
    });
    toast.success(response.message || "Partners copied");
    copyDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to copy partners", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    copySaving.value = false;
  }
};
</script>
