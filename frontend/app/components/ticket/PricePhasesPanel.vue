<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
      <p class="text-muted-foreground text-sm tracking-tight">
        Sell this ticket at different prices over time. Phases must not overlap in their date
        ranges.
      </p>
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Phase
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div
      v-else-if="!phases.length"
      class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No price phases yet. The ticket has no purchasable price until you add one.
    </div>

    <div v-else ref="listContainer" class="space-y-2">
      <div
        v-for="phase in phases"
        :key="phase.id"
        :data-item-id="phase.id"
        class="bg-card flex items-center gap-x-3 rounded-xl border px-3 py-3"
      >
        <Icon
          name="lucide:grip-vertical"
          class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
        />

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-medium tracking-tight">{{ phase.label || "Phase" }}</span>
            <span
              v-if="!phase.is_active"
              class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
            >
              Inactive
            </span>
          </div>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{ phaseWindow(phase) }}
          </p>
        </div>

        <div class="hidden shrink-0 text-right sm:block">
          <p class="text-sm font-semibold tracking-tight tabular-nums">
            Rp{{ formatRupiah(phase.price) }}
          </p>
          <p class="text-muted-foreground text-xs tracking-tight tabular-nums sm:text-sm">
            {{ phaseStock(phase) }}
          </p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button variant="ghost" size="iconSm" v-tippy="'Edit'" @click="openEditDialog(phase)">
            <Icon name="hugeicons:edit-02" class="size-4" />
          </Button>
          <Button
            v-if="canDelete"
            variant="ghost"
            size="iconSm"
            class="hover:bg-destructive/10 text-destructive"
            v-tippy="'Delete'"
            @click="confirmDelete(phase)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </div>
      </div>
    </div>

    <!-- Create / Edit dialog -->
    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="30rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">
            {{ editing ? "Edit Price Phase" : "Add Price Phase" }}
          </h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label for="phase-label">Label</Label>
              <Input id="phase-label" v-model="form.label" placeholder="Early Bird / Regular / On-site" />
              <FieldError :errors="errors.label" />
            </div>

            <div class="space-y-2">
              <Label>Price</Label>
              <InputGroup>
                <InputNumber
                  v-model="form.price"
                  :min="0"
                  required
                  data-slot="input-group-control"
                  class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                />
                <InputGroupAddon>
                  <InputGroupText>Rp</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <FieldError :errors="errors.price" />
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
                <FieldError :errors="errors.starts_at" />
              </div>
              <div class="space-y-2">
                <Label>Ends at</Label>
                <DatePicker
                  with-time
                  :model-value="form._ends_at_obj"
                  placeholder="Optional end"
                  @update:model-value="(d) => (form._ends_at_obj = d)"
                />
                <FieldError :errors="errors.ends_at" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Quota</Label>
              <InputNumber v-model="form.quota" :min="0" placeholder="No quota limit" />
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                Cap the number sold during this phase. Leave empty for no phase quota.
              </p>
              <FieldError :errors="errors.quota" />
            </div>

            <div class="flex items-center gap-2">
              <Switch id="phase-active" v-model="form.is_active" />
              <Label for="phase-active" class="cursor-pointer">Active</Label>
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
          <div class="text-foreground text-lg font-semibold tracking-tighter">Delete price phase?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingItem?.label || "This phase" }}" will be removed.
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
import { FieldError } from "@/components/ui/field";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { useSortableList } from "@/composables/useSortableList";
import { toLocalDateTimeString } from "@/lib/utils";
import { computed, reactive, ref } from "vue";
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
  () => `/api/events/${props.event.id}/tickets/${props.ticket.slug}/price-phases`
);

const { data, pending, refresh } = await useLazySanctumFetch(
  () => `${baseUrl.value}?per_page=100`,
  { key: () => `ticket-${props.event.id}-${props.ticket.slug}-price-phases` }
);

const phases = ref([]);
watch(
  data,
  (v) => {
    phases.value = v?.data ?? [];
  },
  { immediate: true }
);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({
  label: "",
  price: 0,
  quota: null,
  is_active: true,
  _starts_at_obj: null,
  _ends_at_obj: null,
});

const resetForm = () => {
  Object.assign(form, {
    label: "",
    price: 0,
    quota: null,
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

const openEditDialog = (phase) => {
  editing.value = phase;
  errors.value = {};
  Object.assign(form, {
    label: phase.label ?? "",
    price: Number(phase.price) || 0,
    quota: phase.quota ?? null,
    is_active: phase.is_active ?? true,
    _starts_at_obj: phase.starts_at ? new Date(phase.starts_at) : null,
    _ends_at_obj: phase.ends_at ? new Date(phase.ends_at) : null,
  });
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  errors.value = {};
  try {
    const payload = {
      label: form.label || null,
      price: Number(form.price) || 0,
      starts_at: form._starts_at_obj ? toLocalDateTimeString(form._starts_at_obj) : null,
      ends_at: form._ends_at_obj ? toLocalDateTimeString(form._ends_at_obj) : null,
      quota: form.quota === "" || form.quota === undefined ? null : form.quota,
      is_active: form.is_active,
    };

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Price phase updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Price phase created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    // Surface 422 field errors (including the overlap message on starts_at)
    // inside the dialog rather than only a toast.
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

const confirmDelete = (phase) => {
  deletingItem.value = phase;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Price phase deleted");
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
useSortableList(listContainer, phases, {
  enabled: canCreate,
  onReorder: async () => {
    const orders = phases.value.map((p, idx) => ({ id: p.id, order: idx + 1 }));
    try {
      await client(`${baseUrl.value}/reorder`, { method: "POST", body: { orders } });
      phases.value.forEach((p, idx) => (p.order_column = idx + 1));
    } catch (err) {
      toast.error("Failed to reorder phases");
      await refresh();
    }
  },
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

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

const phaseWindow = (phase) => {
  const start = formatDateTime(phase.starts_at);
  const end = formatDateTime(phase.ends_at);
  if (!start && !end) return "Always available";
  if (start && end) return `${start} → ${end}`;
  if (start) return `From ${start}`;
  return `Until ${end}`;
};

const phaseStock = (phase) => {
  const sold = Number(phase.sold_count ?? 0);
  if (phase.quota === null || phase.quota === undefined) return `${sold} sold`;
  return `${sold}/${phase.quota} sold`;
};
</script>
