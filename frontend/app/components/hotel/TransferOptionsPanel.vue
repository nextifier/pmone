<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button v-if="canEdit" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Transfer Option
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div v-else-if="!options.length" class="text-muted-foreground text-sm tracking-tight rounded-md border border-dashed py-10 text-center">
      No transfer options yet.
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2">
      <div v-for="opt in options" :key="opt.id" class="border rounded-md p-4 space-y-2">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="text-sm font-semibold tracking-tight truncate">{{ opt.label }}</h3>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              {{ opt.direction_label }} · {{ opt.vehicle_type || "-" }} · max {{ opt.max_pax }} pax
            </p>
          </div>
          <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight', opt.is_active ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground']">
            {{ opt.is_active ? "Active" : "Inactive" }}
          </span>
        </div>
        <p class="text-sm tracking-tight">
          <span class="font-medium">Rp {{ formatRupiah(opt.price) }}</span>
          <span class="text-muted-foreground"> per trip</span>
        </p>
        <div class="flex justify-end gap-1 pt-1">
          <button class="hover:bg-muted text-muted-foreground inline-flex size-7 items-center justify-center rounded" @click="openEditDialog(opt)" title="Edit">
            <Icon name="lucide:pencil" class="size-3.5" />
          </button>
          <button v-if="canEdit" class="hover:bg-destructive/10 text-destructive inline-flex size-7 items-center justify-center rounded" @click="confirmDelete(opt)" title="Delete">
            <Icon name="lucide:trash" class="size-3.5" />
          </button>
        </div>
      </div>
    </div>

    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="28rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">{{ editing ? "Edit Transfer Option" : "Add Transfer Option" }}</h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Label<span class="text-destructive">*</span></Label>
              <Input v-model="form.label" required placeholder="Airport Sedan (CGK)" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Direction<span class="text-destructive">*</span></Label>
                <select v-model="form.direction" required class="border-input w-full rounded-md border px-3 py-2 text-sm tracking-tight">
                  <option value="in">Arrival (In)</option>
                  <option value="out">Departure (Out)</option>
                  <option value="both">Both Ways</option>
                </select>
              </div>
              <div class="space-y-2">
                <Label>Vehicle Type</Label>
                <Input v-model="form.vehicle_type" placeholder="Sedan / MPV / Van" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Max Pax<span class="text-destructive">*</span></Label>
                <Input v-model.number="form.max_pax" type="number" min="1" required />
              </div>
              <div class="space-y-2">
                <Label>Price (IDR)<span class="text-destructive">*</span></Label>
                <Input v-model.number="form.price" type="number" min="0" required />
              </div>
            </div>

            <label class="flex items-center gap-2 text-sm tracking-tight">
              <Checkbox v-model="form.is_active" />
              <span>Active</span>
            </label>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                {{ editing ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete transfer option?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">"{{ deletingItem?.label }}" will be removed.</p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Icon v-if="deleting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
              Delete
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  hotelSlug: { type: String, required: true },
});

const baseUrl = computed(() => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/transfer-options`);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("hotels.update"));

const { data, pending, refresh } = await useLazySanctumFetch(() => `${baseUrl.value}?per_page=50`, {
  key: () => `hotel-${props.eventId}-${props.hotelSlug}-transfers`,
});

const options = computed(() => data.value?.data ?? []);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);

const form = reactive({
  label: "",
  direction: "both",
  vehicle_type: "",
  max_pax: 2,
  price: 0,
  is_active: true,
});

const resetForm = () => {
  Object.assign(form, {
    label: "",
    direction: "both",
    vehicle_type: "",
    max_pax: 2,
    price: 0,
    is_active: true,
  });
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (opt) => {
  editing.value = opt;
  Object.assign(form, {
    label: opt.label,
    direction: opt.direction,
    vehicle_type: opt.vehicle_type || "",
    max_pax: opt.max_pax,
    price: opt.price,
    is_active: opt.is_active,
  });
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const payload = { ...form };
    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Transfer option updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Transfer option created");
    }
    dialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Save failed", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};

const deleteDialogOpen = ref(false);
const deletingItem = ref(null);
const deleting = ref(false);

const confirmDelete = (opt) => {
  deletingItem.value = opt;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Transfer option deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
