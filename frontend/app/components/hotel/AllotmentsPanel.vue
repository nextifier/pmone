<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Allotment
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div v-else-if="!allotments.length" class="text-muted-foreground text-sm tracking-tight rounded-md border border-dashed py-10 text-center">
      No allotments yet. Create one to commit room block to an event.
    </div>

    <div v-else class="overflow-x-auto rounded-md border">
      <table class="w-full text-sm tracking-tight">
        <thead class="bg-muted">
          <tr class="text-left">
            <th class="px-3 py-2">Room Type</th>
            <th class="px-3 py-2">Date Range</th>
            <th class="px-3 py-2 text-right">Quantity</th>
            <th class="px-3 py-2">Surcharge</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in allotments" :key="a.id" class="border-t">
            <td class="px-3 py-2">{{ a.room_type?.name || "-" }}</td>
            <td class="px-3 py-2 whitespace-nowrap">{{ a.start_date }} → {{ a.end_date }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ a.quantity }}</td>
            <td class="px-3 py-2">{{ a.surcharge_type ? `${a.surcharge_amount} ${a.surcharge_type}` : "-" }}</td>
            <td class="px-3 py-2">
              <span :class="['rounded-full px-2 py-0.5 text-xs tracking-tight', a.is_active ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground']">
                {{ a.is_active ? "Active" : "Inactive" }}
              </span>
            </td>
            <td class="px-3 py-2">
              <div class="flex gap-1 justify-end">
                <button class="hover:bg-muted text-muted-foreground inline-flex size-7 items-center justify-center rounded" @click="openEditDialog(a)" title="Edit">
                  <Icon name="lucide:pencil" class="size-3.5" />
                </button>
                <button v-if="canDelete" class="hover:bg-destructive/10 text-destructive inline-flex size-7 items-center justify-center rounded" @click="confirmDelete(a)" title="Delete">
                  <Icon name="lucide:trash" class="size-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="30rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">{{ editing ? "Edit Allotment" : "Add Allotment" }}</h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label for="allotment_room_type">Room Type<span class="text-destructive">*</span></Label>
              <Select :model-value="form.room_type_id ? String(form.room_type_id) : undefined" @update:model-value="(v) => (form.room_type_id = v ? Number(v) : null)">
                <SelectTrigger id="allotment_room_type" class="w-full">
                  <SelectValue placeholder="Select room type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="r in roomTypes" :key="r.id" :value="String(r.id)">
                    {{ r.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Start Date<span class="text-destructive">*</span></Label>
                <Input v-model="form.start_date" type="date" required />
              </div>
              <div class="space-y-2">
                <Label>End Date<span class="text-destructive">*</span></Label>
                <Input v-model="form.end_date" type="date" required />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Quantity<span class="text-destructive">*</span></Label>
              <Input v-model.number="form.quantity" type="number" min="1" required />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label for="surcharge_type">Surcharge Type</Label>
                <Select :model-value="form.surcharge_type ?? 'none'" @update:model-value="(v) => (form.surcharge_type = v === 'none' ? null : v)">
                  <SelectTrigger id="surcharge_type" class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    <SelectItem value="fixed">Fixed (IDR)</SelectItem>
                    <SelectItem value="percentage">Percentage (%)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label>Surcharge Amount</Label>
                <Input v-model.number="form.surcharge_amount" type="number" step="0.01" min="0" :disabled="!form.surcharge_type" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Release At (auto-release unsold)</Label>
              <Input v-model="form.release_at" type="datetime-local" />
            </div>

            <label class="flex items-center gap-2 text-sm tracking-tight">
              <Checkbox v-model="form.is_active" />
              <span>Active</span>
            </label>

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

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete allotment?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This allotment block will be moved to trash.
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
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  hotelSlug: { type: String, required: true },
});

const baseUrl = computed(() => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/allotments`);
const roomsUrl = computed(() => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/room-types`);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("allotments.create"));
const canDelete = computed(() => hasPermission("allotments.delete"));

const { data, pending, refresh } = await useLazySanctumFetch(() => `${baseUrl.value}?per_page=50`, {
  key: () => `hotel-${props.eventId}-${props.hotelSlug}-allotments`,
});

const allotments = computed(() => data.value?.data ?? []);

const { data: roomsData } = await useLazySanctumFetch(() => `${roomsUrl.value}?per_page=100`, {
  key: () => `hotel-${props.eventId}-${props.hotelSlug}-rooms-for-allotments`,
});
const roomTypes = computed(() => roomsData.value?.data ?? []);

const dialogOpen = ref(false);
const editing = ref(null);
const saving = ref(false);

const form = reactive({
  room_type_id: null,
  start_date: "",
  end_date: "",
  quantity: 1,
  surcharge_type: null,
  surcharge_amount: null,
  release_at: "",
  is_active: true,
});

const resetForm = () => {
  Object.assign(form, {
    room_type_id: null,
    start_date: "",
    end_date: "",
    quantity: 1,
    surcharge_type: null,
    surcharge_amount: null,
    release_at: "",
    is_active: true,
  });
};

const openCreateDialog = () => {
  editing.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (a) => {
  editing.value = a;
  Object.assign(form, {
    room_type_id: a.room_type_id,
    start_date: a.start_date,
    end_date: a.end_date,
    quantity: a.quantity,
    surcharge_type: a.surcharge_type,
    surcharge_amount: a.surcharge_amount,
    release_at: a.release_at ? a.release_at.slice(0, 16) : "",
    is_active: a.is_active,
  });
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const payload = { ...form };
    if (!payload.surcharge_type) {
      payload.surcharge_amount = null;
    }
    if (!payload.release_at) {
      payload.release_at = null;
    }

    if (editing.value) {
      await client(`${baseUrl.value}/${editing.value.id}`, { method: "PUT", body: payload });
      toast.success("Allotment updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Allotment created");
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

const confirmDelete = (a) => {
  deletingItem.value = a;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingItem.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingItem.value.id}`, { method: "DELETE" });
    toast.success("Allotment deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};
</script>
