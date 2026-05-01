<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-5xl xl:max-w-6xl">
    <!-- Page header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:user-multiple-02" class="size-5 sm:size-6" />
        <h1 class="page-title">{{ $t("guests.title") }}</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          v-if="canDelete"
          type="button"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
          @click="openTrash"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span class="hidden sm:inline">{{ $t("guests.trash") }}</span>
        </button>
        <button
          v-if="canCreate"
          type="button"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
          @click="openCreate"
        >
          <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
          <span>{{ $t("guests.addGuest") }}</span>
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div v-if="!loading && rawGuests.length" class="flex flex-wrap items-center gap-2">
      <div class="relative min-w-48 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input v-model="search" :placeholder="$t('guests.searchGuests')" class="pl-9" />
      </div>

      <Select v-model="featuredFilter">
        <SelectTrigger class="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{{ $t("guests.all") }}</SelectItem>
          <SelectItem value="featured">{{ $t("guests.featured") }}</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="visibilityFilter">
        <SelectTrigger class="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{{ $t("guests.all") }}</SelectItem>
          <SelectItem value="public">{{ $t("guests.public") }}</SelectItem>
          <SelectItem value="private">{{ $t("guests.private") }}</SelectItem>
        </SelectContent>
      </Select>

      <button
        v-if="hasActiveFilters"
        type="button"
        class="text-muted-foreground hover:bg-muted rounded-md px-3 py-1.5 text-sm tracking-tight"
        @click="resetFilters"
      >
        Reset
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!rawGuests.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:star" />
        </div>
        <div>
          <Icon name="hugeicons:user-multiple-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:user" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">{{ $t("guests.noGuests") }}</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          {{ $t("guests.noGuestsDescription") }}
        </p>
      </div>
      <button
        v-if="canCreate"
        type="button"
        class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 flex items-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        @click="openCreate"
      >
        <Icon name="hugeicons:plus-sign" class="size-4 shrink-0" />
        <span>{{ $t("guests.createFirst") }}</span>
      </button>
    </div>

    <!-- Filtered empty -->
    <div
      v-else-if="!filteredGuests.length && hasActiveFilters"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No guests match your filters.
      <button type="button" class="text-primary hover:underline" @click="resetFilters">
        Clear filters
      </button>
    </div>

    <!-- Grid -->
    <div
      v-else
      ref="gridContainer"
      class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4"
    >
      <div
        v-for="guest in filteredGuests"
        :key="guest.id"
        :data-guest-id="guest.id"
        class="group border-border bg-background hover:shadow-sm relative flex flex-col overflow-hidden rounded-xl border transition"
      >
        <!-- Drag handle -->
        <span
          class="drag-handle absolute top-2 left-2 z-10 inline-flex size-7 cursor-grab items-center justify-center rounded-md bg-black/40 text-white backdrop-blur-sm opacity-0 transition group-hover:opacity-100 active:cursor-grabbing"
        >
          <Icon name="hugeicons:drag-drop" class="size-3.5" />
        </span>

        <!-- Featured badge -->
        <span
          v-if="guest.is_featured"
          class="bg-warning text-warning-foreground absolute top-2 right-2 z-10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium tracking-tight"
        >
          <Icon name="hugeicons:star" class="size-3" />
          {{ $t("guests.featured") }}
        </span>

        <!-- Visibility badge -->
        <span
          v-if="guest.visibility === 'private'"
          class="bg-muted text-muted-foreground absolute bottom-2 left-2 z-10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs tracking-tight"
        >
          <Icon name="hugeicons:lock" class="size-3" />
          {{ $t("guests.private") }}
        </span>

        <!-- Profile image -->
        <button
          type="button"
          class="bg-muted relative aspect-[4/5] w-full overflow-hidden text-left"
          @click="openEdit(guest)"
        >
          <img
            v-if="guest.profile_image?.md || guest.profile_image?.sm"
            :src="guest.profile_image.md ?? guest.profile_image.sm"
            :alt="guest.name"
            class="size-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="text-muted-foreground flex size-full items-center justify-center"
          >
            <Icon name="hugeicons:user" class="size-8" />
          </div>
        </button>

        <!-- Body -->
        <div class="flex grow flex-col gap-1 p-3">
          <button
            type="button"
            class="line-clamp-2 text-left text-sm font-semibold tracking-tight hover:underline"
            @click="openEdit(guest)"
          >
            {{ guest.name }}
          </button>
          <p
            v-if="resolveTitle(guest)"
            class="text-muted-foreground line-clamp-1 text-xs tracking-tight"
          >
            {{ resolveTitle(guest) }}
          </p>
          <p
            v-if="guest.organization"
            class="text-muted-foreground line-clamp-1 text-xs tracking-tight"
          >
            {{ guest.organization }}
          </p>

          <!-- Action menu -->
          <div class="mt-auto flex items-center justify-end gap-1 pt-1">
            <button
              v-if="guest.can_edit"
              type="button"
              class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
              @click="openEdit(guest)"
              v-tippy="'Edit'"
            >
              <Icon name="lucide:pencil" class="size-3.5" />
            </button>
            <button
              v-if="guest.can_delete"
              type="button"
              class="hover:bg-destructive/10 hover:text-destructive inline-flex size-7 items-center justify-center rounded-md"
              @click="confirmDelete(guest)"
              v-tippy="'Delete'"
            >
              <Icon name="hugeicons:delete-01" class="size-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit dialog -->
    <DialogResponsive v-model:open="formOpen" dialog-max-width="40rem" :overflow-content="true">
      <template #title>
        {{ editingGuest ? $t("guests.editGuest") : $t("guests.addGuest") }}
      </template>
      <template #content>
        <FormGuest
          :key="formKey"
          :guest="editingGuest"
          :loading="formSaving"
          :errors="formErrors"
          @submit="handleSave"
          @cancel="formOpen = false"
        />
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteOpen" dialog-max-width="22rem">
      <template #title>{{ $t("guests.deleteSingleConfirm") }}</template>
      <template #content>
        <div class="space-y-4">
          <p class="text-muted-foreground text-sm tracking-tight">
            {{ deletingGuest?.name }} will be moved to trash.
          </p>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight"
              @click="deleteOpen = false"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="deleteSaving"
              class="bg-destructive text-destructive-foreground hover:bg-destructive/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight disabled:opacity-60"
              @click="handleDelete"
            >
              <Icon v-if="deleteSaving" name="svg-spinners:ring-resize" class="size-4" />
              Delete
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Trash dialog -->
    <DialogResponsive v-model:open="trashOpen" dialog-max-width="36rem" :overflow-content="true">
      <template #title>{{ $t("guests.trash") }}</template>
      <template #content>
        <div class="space-y-3">
          <div v-if="trashLoading" class="flex justify-center py-8">
            <Spinner class="size-5" />
          </div>
          <div
            v-else-if="!trashGuests.length"
            class="text-muted-foreground py-8 text-center text-sm tracking-tight"
          >
            {{ $t("guests.emptyTrash") }}
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="guest in trashGuests"
              :key="guest.id"
              class="border-border flex items-center gap-3 rounded-md border p-2"
            >
              <div class="bg-muted aspect-[4/5] w-10 shrink-0 overflow-hidden rounded">
                <img
                  v-if="guest.profile_image?.sm"
                  :src="guest.profile_image.sm"
                  :alt="guest.name"
                  class="size-full object-cover"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium tracking-tight">{{ guest.name }}</p>
                <p class="text-muted-foreground truncate text-xs tracking-tight">
                  {{ guest.organization || "—" }}
                </p>
              </div>
              <button
                type="button"
                class="text-primary hover:bg-muted rounded-md px-2 py-1 text-sm tracking-tight"
                @click="restoreGuest(guest)"
              >
                Restore
              </button>
              <button
                type="button"
                class="text-destructive hover:bg-destructive/10 rounded-md px-2 py-1 text-sm tracking-tight"
                @click="forceDestroy(guest)"
              >
                Delete forever
              </button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import FormGuest from "@/components/guest/FormGuest.vue";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import Sortable from "sortablejs";
import { toast } from "vue-sonner";

defineProps({ event: Object, project: Object });

const { t } = useI18n();
usePageMeta(null, { title: t("guests.title") });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/guests`;

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("guests.create"));
const canDelete = computed(() => hasPermission("guests.delete"));

// State
const rawGuests = ref([]);
const loading = ref(true);

const search = ref("");
const featuredFilter = ref("all");
const visibilityFilter = ref("all");

const hasActiveFilters = computed(
  () => Boolean(search.value) || featuredFilter.value !== "all" || visibilityFilter.value !== "all"
);

const resetFilters = () => {
  search.value = "";
  featuredFilter.value = "all";
  visibilityFilter.value = "all";
};

const fetchGuests = async () => {
  try {
    loading.value = true;
    const response = await client(`${apiBase}?per_page=200`);
    rawGuests.value = response.data ?? [];
  } catch (err) {
    console.error("Failed to load guests:", err);
    toast.error(t("guests.errorLoading"));
  } finally {
    loading.value = false;
  }
};

onMounted(fetchGuests);

const resolveTitle = (guest) => {
  const t = guest.title;
  if (!t) return "";
  if (typeof t === "string") return t;
  return t.en ?? t.id ?? Object.values(t).find(Boolean) ?? "";
};

const filteredGuests = computed(() => {
  const q = search.value.trim().toLowerCase();
  return rawGuests.value.filter((guest) => {
    if (featuredFilter.value === "featured" && !guest.is_featured) return false;
    if (visibilityFilter.value !== "all" && guest.visibility !== visibilityFilter.value) {
      return false;
    }
    if (q) {
      const haystack = [guest.name, guest.organization, resolveTitle(guest), ...(guest.tags ?? [])]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      if (!haystack.includes(q)) return false;
    }
    return true;
  });
});

// Drag-drop reorder
const gridContainer = ref(null);
let sortableInstance = null;

const initSortable = () => {
  if (sortableInstance) {
    sortableInstance.destroy();
    sortableInstance = null;
  }
  if (!gridContainer.value || hasActiveFilters.value) return;

  sortableInstance = Sortable.create(gridContainer.value, {
    animation: 200,
    handle: ".drag-handle",
    ghostClass: "sortable-ghost",
    chosenClass: "sortable-chosen",
    dragClass: "sortable-drag",
    onEnd: async () => {
      const ids = Array.from(gridContainer.value.querySelectorAll("[data-guest-id]")).map((node) =>
        Number(node.dataset.guestId)
      );
      const orders = ids.map((id, idx) => ({ id, order: idx + 1 }));

      try {
        await client(`${apiBase}/reorder`, {
          method: "POST",
          body: { orders },
        });
        await fetchGuests();
      } catch (err) {
        toast.error("Failed to reorder guests");
      }
    },
  });
};

watch(
  [filteredGuests, hasActiveFilters],
  async () => {
    await nextTick();
    initSortable();
  },
  { deep: false }
);

onUnmounted(() => {
  sortableInstance?.destroy();
});

// Create/edit
const formOpen = ref(false);
const editingGuest = ref(null);
const formSaving = ref(false);
const formErrors = ref({});
const formKey = ref(0);

const openCreate = () => {
  editingGuest.value = null;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const openEdit = (guest) => {
  editingGuest.value = guest;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

const handleSave = async (payload) => {
  formSaving.value = true;
  formErrors.value = {};
  try {
    if (editingGuest.value) {
      await client(`${apiBase}/${editingGuest.value.id}`, { method: "PUT", body: payload });
      toast.success(t("guests.guestSaved"));
    } else {
      await client(apiBase, { method: "POST", body: payload });
      toast.success(t("guests.guestCreated"));
    }
    formOpen.value = false;
    await fetchGuests();
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      formErrors.value = err.data.errors;
    } else {
      toast.error(t("guests.failedToSave"), {
        description: err?.data?.message || err?.message,
      });
    }
  } finally {
    formSaving.value = false;
  }
};

// Delete
const deleteOpen = ref(false);
const deletingGuest = ref(null);
const deleteSaving = ref(false);

const confirmDelete = (guest) => {
  deletingGuest.value = guest;
  deleteOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingGuest.value) return;
  deleteSaving.value = true;
  try {
    await client(`${apiBase}/${deletingGuest.value.id}`, { method: "DELETE" });
    toast.success(t("guests.guestDeleted"));
    deleteOpen.value = false;
    await fetchGuests();
  } catch (err) {
    toast.error(t("guests.failedToDelete"));
  } finally {
    deleteSaving.value = false;
  }
};

// Trash
const trashOpen = ref(false);
const trashGuests = ref([]);
const trashLoading = ref(false);

const openTrash = async () => {
  trashOpen.value = true;
  trashLoading.value = true;
  try {
    const response = await client(`${apiBase}/trash`);
    trashGuests.value = response.data ?? [];
  } catch (err) {
    toast.error("Failed to load trash");
  } finally {
    trashLoading.value = false;
  }
};

const restoreGuest = async (guest) => {
  try {
    await client(`${apiBase}/trash/${guest.id}/restore`, { method: "POST" });
    toast.success(t("guests.guestRestored"));
    trashGuests.value = trashGuests.value.filter((g) => g.id !== guest.id);
    await fetchGuests();
  } catch (err) {
    toast.error("Failed to restore guest");
  }
};

const forceDestroy = async (guest) => {
  try {
    await client(`${apiBase}/trash/${guest.id}`, { method: "DELETE" });
    toast.success("Permanently deleted");
    trashGuests.value = trashGuests.value.filter((g) => g.id !== guest.id);
  } catch (err) {
    toast.error("Failed to permanently delete");
  }
};
</script>
