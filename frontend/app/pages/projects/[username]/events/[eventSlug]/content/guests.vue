<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-5xl xl:max-w-6xl">
    <!-- Page header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:user-multiple-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Guests &amp; Speakers</h1>
        <span
          v-if="!loading && totalCount"
          class="text-muted-foreground text-xs tracking-tight tabular-nums"
        >
          {{ totalCount }}
        </span>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button v-if="canDelete" variant="outline" size="sm" @click="openTrash">
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span class="hidden sm:inline">Trash</span>
        </Button>
        <Button v-if="canCreate" size="sm" @click="openCreate">
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          <span>Add Guest</span>
          <KbdGroup>
            <Kbd>N</Kbd>
          </KbdGroup>
        </Button>
      </div>
    </div>

    <!-- Filter bar -->
    <div v-if="!loading || hasActiveFilters || guests.length" class="flex flex-wrap items-center gap-2">
      <div class="relative min-w-48 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <Input v-model="searchInput" placeholder="Search guests..." class="pl-9" />
      </div>

      <Select v-model="statusFilter">
        <SelectTrigger class="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All</SelectItem>
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="featuredFilter">
        <SelectTrigger class="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All</SelectItem>
          <SelectItem value="featured">Featured</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="visibilityFilter">
        <SelectTrigger class="w-32 shrink-0">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All</SelectItem>
          <SelectItem value="public">Public</SelectItem>
          <SelectItem value="private">Private</SelectItem>
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

    <!-- Bulk action bar -->
    <div
      v-if="selectedCount > 0"
      class="border-border bg-muted/50 sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 backdrop-blur-sm"
    >
      <Checkbox
        :model-value="allLoadedSelected ? true : selectedCount > 0 ? 'indeterminate' : false"
        @update:model-value="toggleSelectAll"
      />
      <span class="text-sm font-medium tracking-tight">{{ selectedCount }} selected</span>
      <div class="ml-auto flex flex-wrap items-center gap-1.5">
        <button
          type="button"
          class="text-muted-foreground hover:bg-muted rounded-md px-2.5 py-1 text-sm tracking-tight"
          @click="clearSelection"
        >
          Clear
        </button>

        <DropdownMenu v-if="canUpdate">
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
              <Icon name="hugeicons:edit-02" class="size-4 shrink-0" />
              Bulk edit
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuLabel>Status</DropdownMenuLabel>
            <DropdownMenuItem @select="applyBulkUpdate({ status: 'active' })">
              Set Active
            </DropdownMenuItem>
            <DropdownMenuItem @select="applyBulkUpdate({ status: 'inactive' })">
              Set Inactive
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel>Visibility</DropdownMenuLabel>
            <DropdownMenuItem @select="applyBulkUpdate({ visibility: 'public' })">
              Set Public
            </DropdownMenuItem>
            <DropdownMenuItem @select="applyBulkUpdate({ visibility: 'private' })">
              Set Private
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuLabel>Featured</DropdownMenuLabel>
            <DropdownMenuItem @select="applyBulkUpdate({ is_featured: true })">
              Mark as Featured
            </DropdownMenuItem>
            <DropdownMenuItem @select="applyBulkUpdate({ is_featured: false })">
              Remove Featured
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <Button v-if="canUpdate" variant="outline" size="sm" @click="moveOpen = true">
          <Icon name="hugeicons:arrow-right-double" class="size-4 shrink-0" />
          Move to event
        </Button>

        <Button v-if="canDelete" variant="destructive" size="sm" @click="bulkDeleteOpen = true">
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          Delete selected
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading && !guests.length" class="flex justify-center py-16">
      <Spinner class="size-6" />
    </div>

    <!-- Empty state (no guests at all) -->
    <div
      v-else-if="!guests.length && !hasActiveFilters && initialLoaded"
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
        <h3 class="font-semibold tracking-tight">No guests yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Add guests or speakers to showcase them on your event website.
        </p>
      </div>
      <Button v-if="canCreate" size="sm" class="mt-2" @click="openCreate">
        <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
        <span>Add your first guest</span>
      </Button>
    </div>

    <!-- Filtered empty state -->
    <div
      v-else-if="!guests.length && hasActiveFilters"
      class="flex flex-col items-center justify-center gap-y-3 py-12 text-center"
    >
      <div
        class="*:bg-background/80 text-muted-foreground flex items-center *:rounded-lg *:border *:p-3 [&_svg]:size-5"
      >
        <div>
          <Icon name="hugeicons:filter" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="text-sm font-semibold tracking-tight">No matching guests</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Try adjusting your filters or search query.
        </p>
      </div>
      <button
        type="button"
        class="text-primary hover:underline text-sm tracking-tight"
        @click="resetFilters"
      >
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
        v-for="guest in guests"
        :key="guest.id"
        :data-guest-id="guest.id"
        :class="[
          'group border-border bg-background hover:shadow-sm relative flex flex-col overflow-hidden rounded-xl border transition',
          isSelected(guest.id) && 'ring-primary ring-2',
        ]"
      >
        <!-- Selection checkbox -->
        <label
          v-if="canDelete || canUpdate"
          :class="[
            'absolute top-2 left-2 z-20 inline-flex size-7 cursor-pointer items-center justify-center rounded-md bg-black/40 backdrop-blur-sm transition',
            isSelected(guest.id) || selectedCount > 0
              ? 'opacity-100'
              : 'opacity-0 group-hover:opacity-100',
          ]"
          @click.stop
        >
          <Checkbox
            :model-value="isSelected(guest.id)"
            class="border-white/60 data-[state=checked]:border-primary"
            @update:model-value="toggleSelect(guest.id)"
          />
        </label>

        <!-- Drag handle -->
        <span
          v-if="!hasActiveFilters && selectedCount === 0"
          class="drag-handle absolute top-2 right-2 z-10 inline-flex size-7 cursor-grab items-center justify-center rounded-md bg-black/40 text-white opacity-0 backdrop-blur-sm transition group-hover:opacity-100 active:cursor-grabbing"
        >
          <Icon name="hugeicons:drag-drop" class="size-3.5" />
        </span>

        <!-- Featured badge -->
        <span
          v-if="guest.is_featured"
          :class="[
            'bg-warning text-warning-foreground absolute z-10 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium tracking-tight',
            !hasActiveFilters && selectedCount === 0 ? 'top-10 right-2' : 'top-2 right-2',
          ]"
        >
          <Icon name="hugeicons:star" class="size-3" />
          Featured
        </span>

        <!-- Status / Visibility badge -->
        <div class="absolute bottom-2 left-2 z-10 flex items-center gap-1">
          <span
            v-if="guest.status === 'inactive'"
            class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs tracking-tight"
          >
            <Icon name="hugeicons:pause" class="size-3" />
            Inactive
          </span>
          <span
            v-if="guest.visibility === 'private'"
            class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs tracking-tight"
          >
            <Icon name="hugeicons:lock" class="size-3" />
            Private
          </span>
        </div>

        <!-- Profile image -->
        <button
          type="button"
          class="bg-muted relative aspect-[4/5] w-full overflow-hidden text-left"
          @click="onCardClick(guest, $event)"
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
            @click="onCardClick(guest, $event)"
          >
            {{ guest.name }}
          </button>
          <p
            v-if="guest.title"
            class="text-muted-foreground line-clamp-1 text-xs tracking-tight"
          >
            {{ guest.title }}
          </p>
          <p
            v-if="guest.organization"
            class="text-muted-foreground line-clamp-1 text-xs tracking-tight"
          >
            {{ guest.organization }}
          </p>

          <!-- Action menu -->
          <div class="mt-auto flex items-center justify-end gap-0.5 pt-1">
            <button
              v-if="guest.can_edit"
              type="button"
              class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
              @click="quickToggle(guest, 'is_featured', !guest.is_featured)"
              v-tippy="guest.is_featured ? 'Remove Featured' : 'Mark as Featured'"
            >
              <Icon
                name="hugeicons:star"
                :class="['size-3.5', guest.is_featured && 'text-warning']"
              />
            </button>
            <button
              v-if="guest.can_edit"
              type="button"
              class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
              @click="quickToggle(guest, 'status', guest.status === 'active' ? 'inactive' : 'active')"
              v-tippy="guest.status === 'active' ? 'Mark as Inactive' : 'Mark as Active'"
            >
              <Icon
                :name="guest.status === 'active' ? 'hugeicons:view' : 'hugeicons:view-off'"
                class="size-3.5"
              />
            </button>
            <button
              v-if="guest.can_edit && canCreate"
              type="button"
              class="hover:bg-muted inline-flex size-7 items-center justify-center rounded-md"
              @click="duplicateGuest(guest)"
              v-tippy="'Duplicate'"
            >
              <Icon name="hugeicons:copy-01" class="size-3.5" />
            </button>
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

    <!-- Pagination -->
    <div
      v-if="!loading && totalPages > 1"
      class="flex flex-col items-center gap-3 pt-2 sm:flex-row sm:justify-between"
    >
      <div class="text-muted-foreground text-xs tracking-tight tabular-nums">
        Showing {{ pageRangeFrom }}–{{ pageRangeTo }} of {{ totalCount }}
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          :disabled="page === 1"
          class="border-border hover:bg-muted rounded-md border px-2.5 py-1 text-xs tracking-tight disabled:opacity-50"
          @click="page = Math.max(1, page - 1)"
        >
          Previous
        </button>
        <span class="text-xs tracking-tight tabular-nums">{{ page }} / {{ totalPages }}</span>
        <button
          type="button"
          :disabled="page >= totalPages"
          class="border-border hover:bg-muted rounded-md border px-2.5 py-1 text-xs tracking-tight disabled:opacity-50"
          @click="page = Math.min(totalPages, page + 1)"
        >
          Next
        </button>
        <Select v-model.number="pageSize">
          <SelectTrigger class="h-7 w-20 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="20">20</SelectItem>
            <SelectItem :value="50">50</SelectItem>
            <SelectItem :value="100">100</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <!-- Create/Edit dialog -->
    <DialogResponsive v-model:open="formOpen" dialog-max-width="40rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="flex items-start justify-between gap-3">
            <h3 class="text-lg font-semibold tracking-tight">
              {{ editingGuest ? "Edit Guest" : "Add Guest" }}
            </h3>
            <button
              v-if="editingGuest"
              type="button"
              class="text-muted-foreground hover:bg-muted inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs tracking-tight"
              @click="activityOpen = true"
            >
              <Icon name="hugeicons:clock-04" class="size-3.5" />
              Activity log
            </button>
          </div>
          <div class="mt-4">
            <FormGuest
              :key="formKey"
              :guest="editingGuest"
              :loading="formSaving"
              :errors="formErrors"
              @submit="handleSave"
              @cancel="formOpen = false"
            />
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Activity log dialog -->
    <DialogResponsive v-model:open="activityOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Activity log</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            {{ editingGuest?.name }}
          </p>
          <div class="mt-4">
            <ActivityLogPanel
              v-if="activityOpen && editingGuest"
              :guest-id="editingGuest.id"
              :api-base="apiBase"
            />
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Move to event dialog -->
    <DialogResponsive v-model:open="moveOpen" dialog-max-width="26rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <MoveToEventDialog
            v-if="moveOpen"
            :username="username"
            :current-event-id="event?.id ?? 0"
            :count="selectedCount"
            :loading="moveSaving"
            @cancel="moveOpen = false"
            @submit="handleMove"
          />
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteOpen" dialog-max-width="22rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Delete this guest?</h3>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            {{ deletingGuest?.name }} will be moved to trash.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="deleteOpen = false">Cancel</Button>
            <Button variant="destructive" size="sm" :disabled="deleteSaving" @click="handleDelete">
              <Icon v-if="deleteSaving" name="svg-spinners:ring-resize" class="size-4" />
              Delete
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Bulk delete confirmation -->
    <DialogResponsive
      v-model:open="bulkDeleteOpen"
      dialog-max-width="24rem"
      :prevent-close="bulkDeleteJob.processing.value"
    >
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Delete selected guests?</h3>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            {{ selectedCount }} guest(s) will be moved to trash.
          </p>

          <div v-if="bulkDeleteJob.processing.value" class="mt-4 space-y-2">
            <div class="bg-muted h-2 overflow-hidden rounded-full">
              <div
                class="bg-destructive h-full transition-all"
                :style="{ width: `${bulkDeleteJob.progress.value?.percentage ?? 0}%` }"
              />
            </div>
            <p class="text-muted-foreground text-xs tracking-tight tabular-nums">
              {{ bulkDeleteJob.progress.value?.processed ?? 0 }} / {{ bulkDeleteJob.progress.value?.total ?? 0 }}
            </p>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <Button
              variant="outline"
              size="sm"
              :disabled="bulkDeleteJob.processing.value"
              @click="bulkDeleteOpen = false"
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              size="sm"
              :disabled="bulkDeleteJob.processing.value"
              @click="handleBulkDelete"
            >
              <Icon v-if="bulkDeleteJob.processing.value" name="svg-spinners:ring-resize" class="size-4" />
              {{ bulkDeleteJob.processing.value ? "Deleting..." : "Delete selected" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Trash dialog -->
    <DialogResponsive v-model:open="trashOpen" dialog-max-width="36rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Trash</h3>
          <div class="mt-4 space-y-3">
            <div v-if="trashLoading" class="flex justify-center py-8">
              <Spinner class="size-5" />
            </div>
            <div
              v-else-if="!trashGuests.length"
              class="text-muted-foreground py-8 text-center text-sm tracking-tight"
            >
              Trash is empty
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
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import ActivityLogPanel from "@/components/guest/ActivityLogPanel.vue";
import FormGuest from "@/components/guest/FormGuest.vue";
import MoveToEventDialog from "@/components/guest/MoveToEventDialog.vue";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { refDebounced } from "@vueuse/core";
import Sortable from "sortablejs";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Guests & Speakers" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/guests`;

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("guests.create"));
const canUpdate = computed(() => hasPermission("guests.update"));
const canDelete = computed(() => hasPermission("guests.delete"));

// State
const guests = ref([]);
const totalCount = ref(0);
const loading = ref(true);
const initialLoaded = ref(false);

const searchInput = ref("");
const search = refDebounced(searchInput, 300);
const statusFilter = ref("all");
const featuredFilter = ref("all");
const visibilityFilter = ref("all");

const page = ref(1);
const pageSize = ref(50);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / pageSize.value)));
const pageRangeFrom = computed(() => (totalCount.value === 0 ? 0 : (page.value - 1) * pageSize.value + 1));
const pageRangeTo = computed(() => Math.min(page.value * pageSize.value, totalCount.value));

const hasActiveFilters = computed(
  () =>
    Boolean(search.value) ||
    statusFilter.value !== "all" ||
    featuredFilter.value !== "all" ||
    visibilityFilter.value !== "all"
);

const resetFilters = () => {
  searchInput.value = "";
  statusFilter.value = "all";
  featuredFilter.value = "all";
  visibilityFilter.value = "all";
  page.value = 1;
};

const buildQuery = () => {
  const params = new URLSearchParams();
  params.set("per_page", String(pageSize.value));
  params.set("page", String(page.value));
  if (search.value) params.set("search", search.value);
  if (statusFilter.value !== "all") params.set("status", statusFilter.value);
  if (visibilityFilter.value !== "all") params.set("visibility", visibilityFilter.value);
  if (featuredFilter.value === "featured") params.set("is_featured", "1");
  return params.toString();
};

const fetchGuests = async () => {
  try {
    loading.value = true;
    const response = await client(`${apiBase}?${buildQuery()}`);
    guests.value = response.data ?? [];
    totalCount.value = response.meta?.total ?? 0;
  } catch (err) {
    console.error("Failed to load guests:", err);
    toast.error("Failed to load guests");
  } finally {
    loading.value = false;
    initialLoaded.value = true;
  }
};

onMounted(fetchGuests);

watch([search, statusFilter, featuredFilter, visibilityFilter], () => {
  page.value = 1;
  fetchGuests();
});

watch([page, pageSize], () => fetchGuests());

// Selection
const selectedIds = ref(new Set());
const selectedCount = computed(() => selectedIds.value.size);
const isSelected = (id) => selectedIds.value.has(id);

const toggleSelect = (id) => {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
};

const clearSelection = () => {
  selectedIds.value = new Set();
};

const allLoadedSelected = computed(
  () => guests.value.length > 0 && guests.value.every((g) => selectedIds.value.has(g.id))
);

const toggleSelectAll = () => {
  if (allLoadedSelected.value) {
    clearSelection();
  } else {
    const next = new Set(selectedIds.value);
    guests.value.forEach((g) => next.add(g.id));
    selectedIds.value = next;
  }
};

const onCardClick = (guest, event) => {
  if (selectedCount.value > 0) {
    event.preventDefault();
    toggleSelect(guest.id);
    return;
  }
  openEdit(guest);
};

// Drag-drop reorder
const gridContainer = ref(null);
let sortableInstance = null;

const initSortable = () => {
  if (sortableInstance) {
    sortableInstance.destroy();
    sortableInstance = null;
  }
  if (!gridContainer.value || hasActiveFilters.value || selectedCount.value > 0) return;

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
      const startIndex = (page.value - 1) * pageSize.value;
      const orders = ids.map((id, idx) => ({ id, order: startIndex + idx + 1 }));

      try {
        await client(`${apiBase}/reorder`, { method: "POST", body: { orders } });
        await fetchGuests();
      } catch {
        toast.error("Failed to reorder guests");
      }
    },
  });
};

watch([guests, hasActiveFilters, selectedCount], async () => {
  await nextTick();
  initSortable();
}, { deep: false });

onUnmounted(() => sortableInstance?.destroy());

// Quick toggle (optimistic)
const quickToggle = async (guest, field, value) => {
  const previous = guest[field];
  guest[field] = value;
  try {
    await client(`${apiBase}/${guest.id}`, {
      method: "PUT",
      body: { [field]: value },
    });
  } catch (err) {
    guest[field] = previous;
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  }
};

// Create/edit
const formOpen = ref(false);
const editingGuest = ref(null);
const formSaving = ref(false);
const formErrors = ref({});
const formKey = ref(0);
const activityOpen = ref(false);

const openCreate = () => {
  editingGuest.value = null;
  formErrors.value = {};
  formKey.value++;
  formOpen.value = true;
};

defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value && !formOpen.value && !trashOpen.value && !bulkDeleteOpen.value && !moveOpen.value && !deleteOpen.value && !activityOpen.value) {
        openCreate();
      }
    },
  },
});

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
      toast.success("Guest saved successfully");
    } else {
      await client(apiBase, { method: "POST", body: payload });
      toast.success("Guest created successfully");
    }
    formOpen.value = false;
    await fetchGuests();
  } catch (err) {
    if (err?.status === 422 && err?.data?.errors) {
      formErrors.value = err.data.errors;
    } else {
      toast.error("Failed to save guest", {
        description: err?.data?.message || err?.message,
      });
    }
  } finally {
    formSaving.value = false;
  }
};

// Duplicate
const duplicateGuest = async (guest) => {
  try {
    await client(`${apiBase}/${guest.id}/duplicate`, { method: "POST" });
    toast.success("Guest duplicated");
    await fetchGuests();
  } catch (err) {
    toast.error("Failed to duplicate guest", {
      description: err?.data?.message || err?.message,
    });
  }
};

// Delete (optimistic)
const deleteOpen = ref(false);
const deletingGuest = ref(null);
const deleteSaving = ref(false);

const confirmDelete = (guest) => {
  deletingGuest.value = guest;
  deleteOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingGuest.value) return;
  const target = deletingGuest.value;
  const snapshot = guests.value.slice();
  guests.value = guests.value.filter((g) => g.id !== target.id);
  totalCount.value = Math.max(0, totalCount.value - 1);
  deleteSaving.value = true;
  try {
    await client(`${apiBase}/${target.id}`, { method: "DELETE" });
    toast.success("Guest deleted successfully");
    deleteOpen.value = false;
    if (selectedIds.value.has(target.id)) {
      const next = new Set(selectedIds.value);
      next.delete(target.id);
      selectedIds.value = next;
    }
  } catch (err) {
    guests.value = snapshot;
    totalCount.value = totalCount.value + 1;
    toast.error("Failed to delete guest", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deleteSaving.value = false;
  }
};

// Bulk delete (queued job)
const bulkDeleteOpen = ref(false);
const bulkDeleteJob = useJobProgress();

watch(
  () => bulkDeleteJob.progress.value?.status,
  (status) => {
    if (status === "completed") {
      const count = bulkDeleteJob.progress.value?.deleted_count ?? selectedCount.value;
      toast.success(`${count} guest(s) deleted`);
      bulkDeleteOpen.value = false;
      clearSelection();
      bulkDeleteJob.reset();
      fetchGuests();
    }
    if (status === "failed") {
      toast.error("Failed to delete selected guests", {
        description: bulkDeleteJob.progress.value?.error_message,
      });
      bulkDeleteJob.reset();
    }
  }
);

const handleBulkDelete = async () => {
  if (selectedCount.value === 0) return;
  try {
    await bulkDeleteJob.startJob(`${apiBase}/bulk`, {
      method: "DELETE",
      body: { ids: Array.from(selectedIds.value) },
    });
  } catch (err) {
    toast.error("Failed to delete selected guests", {
      description: err?.data?.message || err?.message,
    });
    bulkDeleteJob.reset();
  }
};

// Bulk update
const applyBulkUpdate = async (payload) => {
  if (selectedCount.value === 0) return;
  try {
    const response = await client(`${apiBase}/bulk`, {
      method: "PATCH",
      body: { ids: Array.from(selectedIds.value), ...payload },
    });
    const count = response?.updated_count ?? selectedCount.value;
    toast.success(response?.message || `${count} guest(s) updated`);
    clearSelection();
    await fetchGuests();
  } catch (err) {
    toast.error("Failed to update selected guests", {
      description: err?.data?.message || err?.message,
    });
  }
};

// Bulk move
const moveOpen = ref(false);
const moveSaving = ref(false);

const handleMove = async (targetEventId) => {
  moveSaving.value = true;
  try {
    const response = await client(`${apiBase}/bulk-move`, {
      method: "POST",
      body: { ids: Array.from(selectedIds.value), target_event_id: targetEventId },
    });
    const count = response?.moved_count ?? selectedCount.value;
    toast.success(`${count} guest(s) moved`);
    moveOpen.value = false;
    clearSelection();
    await fetchGuests();
  } catch (err) {
    toast.error("Failed to move guests", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    moveSaving.value = false;
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
  } catch {
    toast.error("Failed to load trash");
  } finally {
    trashLoading.value = false;
  }
};

const restoreGuest = async (guest) => {
  try {
    await client(`${apiBase}/trash/${guest.id}/restore`, { method: "POST" });
    toast.success("Guest restored");
    trashGuests.value = trashGuests.value.filter((g) => g.id !== guest.id);
    await fetchGuests();
  } catch {
    toast.error("Failed to restore guest");
  }
};

const forceDestroy = async (guest) => {
  try {
    await client(`${apiBase}/trash/${guest.id}`, { method: "DELETE" });
    toast.success("Permanently deleted");
    trashGuests.value = trashGuests.value.filter((g) => g.id !== guest.id);
  } catch {
    toast.error("Failed to permanently delete");
  }
};
</script>
