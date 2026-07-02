<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Trashed Hotels</h1>
      </div>

      <div v-if="selectedIds.length === 0" class="flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          to="/hotels-master"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          <span>Back to all hotels</span>
        </NuxtLink>
      </div>

      <div v-else class="flex shrink-0 flex-wrap items-center gap-1 sm:gap-2">
        <span class="text-muted-foreground text-sm tracking-tight">
          {{ selectedIds.length }} selected
        </span>
        <Button variant="outline" size="sm" :disabled="bulkBusy" @click="bulkRestore">
          <Spinner v-if="bulkBusy && bulkAction === 'restore'" class="size-3.5" />
          <Icon v-else name="hugeicons:undo-02" class="size-4 shrink-0" />
          Restore selected
        </Button>
        <Button
          variant="destructive"
          size="sm"
          :disabled="bulkBusy"
          @click="bulkForceConfirm = true"
        >
          <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
          Delete selected
        </Button>
        <Button variant="outline" size="sm" @click="selectedIds = []">
          <Icon name="lucide:x" class="size-4 shrink-0" />
          Clear
        </Button>
      </div>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <Empty v-else-if="!hotels.length" class="border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:delete-01" />
        </EmptyMedia>
        <EmptyTitle>Trash is empty</EmptyTitle>
        <EmptyDescription>
          Deleted hotels will appear here so you can restore or remove them permanently.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="rounded-md border">
      <div class="border-border bg-muted/30 flex items-center gap-3 border-b px-4 py-2">
        <Checkbox
          :id="'select-all'"
          :model-value="allSelected"
          @update:model-value="toggleAll"
        />
        <Label for="select-all" class="text-muted-foreground cursor-pointer text-xs tracking-tight">
          {{ allSelected ? "Deselect all" : "Select all" }}
        </Label>
      </div>
      <ul class="divide-border divide-y">
        <li
          v-for="hotel in hotels"
          :key="hotel.id"
          class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
        >
          <div class="flex min-w-0 items-center gap-3">
            <Checkbox
              :id="`select-${hotel.id}`"
              :model-value="selectedIds.includes(hotel.id)"
              @update:model-value="(v) => toggleOne(hotel.id, v)"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium tracking-tight">{{ hotel.name }}</p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ hotel.slug }}<span v-if="hotel.deleted_at">
                  · deleted {{ $dayjs(hotel.deleted_at).fromNow() }}</span
                >
              </p>
            </div>
          </div>
          <div class="flex items-center gap-x-2">
            <Button variant="outline" size="sm" :disabled="busyId === hotel.id" @click="restore(hotel)">
              <Spinner v-if="busyId === hotel.id" class="size-3.5" />
              <Icon v-else name="hugeicons:undo-02" class="size-4 shrink-0" />
              Restore
            </Button>
            <Button
              variant="destructive"
              size="sm"
              :disabled="busyId === hotel.id"
              @click="confirmForce(hotel)"
            >
              <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
              Delete permanently
            </Button>
          </div>
        </li>
      </ul>
    </div>

    <DialogResponsive v-model:open="bulkForceConfirm">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">
            Permanently delete {{ selectedIds.length }} hotel{{ selectedIds.length === 1 ? "" : "s" }}?
          </div>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
            All selected hotels and their related media, allotments, and pivot rows will be deleted
            forever. This cannot be undone.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="bulkForceConfirm = false">Cancel</Button>
            <Button variant="destructive" :disabled="bulkBusy" @click="bulkForceDelete">
              <Spinner v-if="bulkBusy && bulkAction === 'force'" />
              Delete forever
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="forceDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">
            Permanently delete hotel?
          </div>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
            "{{ forceTarget?.name }}" will be deleted forever along with all related media,
            allotments, and pivot rows. This cannot be undone.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="forceDialogOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="busyId === forceTarget?.id" @click="forceDelete">
              <Spinner v-if="busyId === forceTarget?.id" />
              Delete forever
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.delete"],
  layout: "app",
});

usePageMeta(null, { title: "Trashed Hotels · Master" });

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

const { data, pending, refresh } = await useLazySanctumFetch("/api/hotels/trash", {
  key: "hotels-master-trash",
});

const hotels = computed(() => data.value?.data ?? []);

const busyId = ref(null);
const forceDialogOpen = ref(false);
const forceTarget = ref(null);

const selectedIds = ref([]);
const bulkBusy = ref(false);
const bulkAction = ref(null);
const bulkForceConfirm = ref(false);

const allSelected = computed(
  () => hotels.value.length > 0 && selectedIds.value.length === hotels.value.length
);

const toggleAll = (checked) => {
  selectedIds.value = checked ? hotels.value.map((h) => h.id) : [];
};

const toggleOne = (id, checked) => {
  selectedIds.value = checked
    ? [...selectedIds.value, id]
    : selectedIds.value.filter((i) => i !== id);
};

const bulkRestore = async () => {
  if (!selectedIds.value.length) return;
  bulkBusy.value = true;
  bulkAction.value = "restore";
  const ids = [...selectedIds.value];
  try {
    await Promise.all(
      ids.map((id) => client(`/api/hotels/trash/${id}/restore`, { method: "POST" }))
    );
    toast.success(`${ids.length} hotel${ids.length === 1 ? "" : "s"} restored`);
    selectedIds.value = [];
    await refresh();
  } catch (err) {
    toast.error("Bulk restore failed", { description: err?.data?.message || err?.message });
  } finally {
    bulkBusy.value = false;
    bulkAction.value = null;
  }
};

const bulkForceDelete = async () => {
  if (!selectedIds.value.length) return;
  bulkBusy.value = true;
  bulkAction.value = "force";
  const ids = [...selectedIds.value];
  try {
    await Promise.all(
      ids.map((id) => client(`/api/hotels/trash/${id}`, { method: "DELETE" }))
    );
    toast.success(`${ids.length} hotel${ids.length === 1 ? "" : "s"} permanently deleted`);
    selectedIds.value = [];
    bulkForceConfirm.value = false;
    await refresh();
  } catch (err) {
    toast.error("Bulk delete failed", { description: err?.data?.message || err?.message });
  } finally {
    bulkBusy.value = false;
    bulkAction.value = null;
  }
};

const restore = async (hotel) => {
  busyId.value = hotel.id;
  try {
    await client(`/api/hotels/trash/${hotel.id}/restore`, { method: "POST" });
    toast.success("Hotel restored");
    await refresh();
  } catch (err) {
    toast.error("Restore failed", { description: err?.data?.message || err?.message });
  } finally {
    busyId.value = null;
  }
};

const confirmForce = (hotel) => {
  forceTarget.value = hotel;
  forceDialogOpen.value = true;
};

const forceDelete = async () => {
  if (!forceTarget.value) return;
  busyId.value = forceTarget.value.id;
  try {
    await client(`/api/hotels/trash/${forceTarget.value.id}`, { method: "DELETE" });
    toast.success("Hotel permanently deleted");
    forceDialogOpen.value = false;
    forceTarget.value = null;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    busyId.value = null;
  }
};
</script>
