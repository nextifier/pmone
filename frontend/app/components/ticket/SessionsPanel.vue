<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
      <p class="text-muted-foreground text-sm tracking-tight">
        Scheduled sessions buyers of this add-on can attend. Each session has its own time and
        capacity.
      </p>
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Session
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div
      v-else-if="!sessions.length"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No sessions yet.
    </div>

    <div v-else ref="listContainer" class="grid gap-3 sm:grid-cols-2">
      <div
        v-for="session in sessions"
        :key="session.id"
        :data-item-id="session.id"
        class="bg-card flex items-start gap-x-3 rounded-xl border p-3 sm:p-4"
      >
        <Icon
          name="lucide:grip-vertical"
          class="drag-handle text-muted-foreground mt-1 size-4 shrink-0 cursor-grab"
        />

        <div class="min-w-0 flex-1 space-y-1.5">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-base font-semibold tracking-tighter">{{ session.label || "Session" }}</span>
            <span
              v-if="!session.is_active"
              class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
            >
              Inactive
            </span>
          </div>

          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{ sessionWindow(session) }}
          </p>

          <div
            class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tracking-tight sm:text-sm"
          >
            <span v-if="session.location" class="inline-flex items-center gap-1">
              <Icon name="hugeicons:location-01" class="size-3.5" />
              {{ session.location }}
            </span>
            <span v-if="session.host" class="inline-flex items-center gap-1">
              <Icon name="hugeicons:user-multiple" class="size-3.5" />
              {{ session.host }}
            </span>
            <span class="inline-flex items-center gap-1">
              <Icon name="hugeicons:ticket-01" class="size-3.5" />
              {{ sessionCapacity(session) }}
            </span>
          </div>

          <div class="flex items-center justify-end gap-1 pt-1">
            <Button variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(session)">
              <Icon name="hugeicons:edit-02" class="size-4" />
            </Button>
            <Button
              v-if="canDelete"
              variant="ghost"
              size="iconSm"
              class="hover:bg-destructive/10 text-destructive"
              v-tippy="'Delete'"
              @click="confirmDelete(session)"
            >
              <Icon name="hugeicons:delete-02" class="size-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="30rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit Session" : "Add Session" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label for="session-label">Label</Label>
              <Input id="session-label" v-model="form.label" placeholder="Keynote / Workshop A" required />
              <InputErrorMessage :errors="errors.label" />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div class="space-y-2">
                <Label>Starts at</Label>
                <DatePicker
                  with-time
                  :model-value="form._starts_at_obj"
                  placeholder="Optional start"
                  @update:model-value="(d) => (form._starts_at_obj = d)"
                />
                <InputErrorMessage :errors="errors.starts_at" />
              </div>
              <div class="space-y-2">
                <Label>Ends at</Label>
                <DatePicker
                  with-time
                  :model-value="form._ends_at_obj"
                  placeholder="Optional end"
                  @update:model-value="(d) => (form._ends_at_obj = d)"
                />
                <InputErrorMessage :errors="errors.ends_at" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="session-location">Location</Label>
              <Input id="session-location" v-model="form.location" placeholder="Hall B, Level 2" />
              <InputErrorMessage :errors="errors.location" />
            </div>

            <div class="space-y-2">
              <Label for="session-host">Host</Label>
              <Input id="session-host" v-model="form.host" placeholder="Speaker or facilitator" />
              <InputErrorMessage :errors="errors.host" />
            </div>

            <div class="space-y-2">
              <Label>Capacity</Label>
              <InputNumber v-model="form.capacity" :min="0" placeholder="Unlimited" />
              <InputErrorMessage :errors="errors.capacity" />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="session-active" v-model="form.is_active" />
              <Label for="session-active" class="cursor-pointer">Active</Label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete confirmation -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tighter">Delete session?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label || "This session" }}" will be removed.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { useSortableList } from "@/composables/useSortableList";
import { toLocalDateTimeString } from "@/lib/utils";
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  event: { type: Object, required: true },
  ticket: { type: Object, required: true },
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("tickets.update"));
const canDelete = computed(() => hasPermission("tickets.update"));

const baseUrl = computed(
  () => `/api/events/${props.event.id}/tickets/${props.ticket.slug}/sessions`
);

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `${baseUrl.value}?per_page=100`,
  { key: () => `ticket-${props.event.id}-${props.ticket.slug}-sessions` }
);

const sessions = ref([]);
watch(
  data,
  (v) => {
    sessions.value = v?.data ?? [];
  },
  { immediate: true }
);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({
  label: "",
  location: "",
  host: "",
  capacity: null,
  is_active: true,
  _starts_at_obj: null,
  _ends_at_obj: null,
});

const resetForm = () => {
  Object.assign(form, {
    label: "",
    location: "",
    host: "",
    capacity: null,
    is_active: true,
    _starts_at_obj: null,
    _ends_at_obj: null,
  });
  errors.value = {};
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (session) => {
  editing.value = session;
  errors.value = {};
  Object.assign(form, {
    label: session.label ?? "",
    location: session.location ?? "",
    host: session.host ?? "",
    capacity: session.capacity ?? null,
    is_active: session.is_active ?? true,
    _starts_at_obj: session.starts_at ? new Date(session.starts_at) : null,
    _ends_at_obj: session.ends_at ? new Date(session.ends_at) : null,
  });
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  errors.value = {};
  try {
    const payload = {
      label: form.label || null,
      starts_at: form._starts_at_obj ? toLocalDateTimeString(form._starts_at_obj) : null,
      ends_at: form._ends_at_obj ? toLocalDateTimeString(form._ends_at_obj) : null,
      location: form.location || null,
      host: form.host || null,
      capacity: form.capacity === "" || form.capacity === undefined ? null : form.capacity,
      is_active: form.is_active,
    };

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Session updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Session created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    // Surface 422 field errors (starts_at outside event range, label when the
    // ticket is entry kind) inside the dialog.
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (session) => {
  deletingItem.value = session;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Session deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

// --- Drag reorder ---
const listContainer = ref(null);
useSortableList(listContainer, sessions, {
  enabled: canCreate,
  onReorder: async () => {
    const orders = sessions.value.map((s, idx) => ({ id: s.id, order: idx + 1 }));
    try {
      await client(`${baseUrl.value}/reorder`, { method: "POST", body: { orders } });
      sessions.value.forEach((s, idx) => (s.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder sessions");
      await refresh();
    }
  },
});

const formatDateTime = (iso) =>
  iso
    ? new Date(iso).toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      })
    : null;

const sessionWindow = (session) => {
  const start = formatDateTime(session.starts_at);
  const end = formatDateTime(session.ends_at);
  if (!start && !end) return "No scheduled time";
  if (start && end) return `${start} → ${end}`;
  if (start) return `From ${start}`;
  return `Until ${end}`;
};

const sessionCapacity = (session) => {
  const booked = Number(session.booked_count ?? 0);
  if (session.capacity === null || session.capacity === undefined) return `${booked} booked`;
  return `${booked}/${session.capacity} booked`;
};
</script>
