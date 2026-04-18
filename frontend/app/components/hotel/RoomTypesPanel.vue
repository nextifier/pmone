<template>
  <div class="space-y-4">
    <div class="flex justify-end">
      <Button v-if="canCreate" size="sm" @click="openCreateDialog">
        <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
        Add Room Type
      </Button>
    </div>

    <div v-if="pending" class="flex justify-center py-6">
      <Spinner class="size-5" />
    </div>

    <div v-else-if="!rooms.length" class="text-muted-foreground text-sm tracking-tight rounded-md border border-dashed py-10 text-center">
      No room types yet.
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2">
      <div
        v-for="room in rooms"
        :key="room.id"
        class="border rounded-md p-4 space-y-2"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="text-sm font-semibold tracking-tight truncate">{{ room.name }}</h3>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              {{ room.bed_type || "-" }} · max {{ room.max_pax }} pax · {{ room.area_sqm ? `${room.area_sqm} m²` : "-" }}
            </p>
          </div>
          <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight', room.is_active ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground']">
            {{ room.is_active ? "Active" : "Inactive" }}
          </span>
        </div>

        <p class="text-sm tracking-tight">
          <span class="font-medium">Rp {{ formatRupiah(room.base_rate) }}</span>
          <span class="text-muted-foreground"> / night</span>
        </p>

        <div v-if="room.amenities?.length" class="flex flex-wrap gap-1">
          <span v-for="amenity in room.amenities.slice(0, 4)" :key="amenity" class="bg-muted rounded-full px-2 py-0.5 text-xs tracking-tight">{{ amenity }}</span>
        </div>

        <div class="flex justify-end gap-1 pt-2">
          <button class="hover:bg-muted text-muted-foreground inline-flex size-7 items-center justify-center rounded" @click="openEditDialog(room)" title="Edit">
            <Icon name="lucide:pencil" class="size-3.5" />
          </button>
          <button v-if="canDelete" class="hover:bg-destructive/10 text-destructive inline-flex size-7 items-center justify-center rounded" @click="confirmDelete(room)" title="Delete">
            <Icon name="lucide:trash" class="size-3.5" />
          </button>
        </div>
      </div>
    </div>

    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">{{ editingRoom ? "Edit Room Type" : "Add Room Type" }}</h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Name<span class="text-destructive">*</span></Label>
              <Input v-model="form.name" required placeholder="Deluxe King" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Max Pax<span class="text-destructive">*</span></Label>
                <Input v-model.number="form.max_pax" type="number" min="1" required />
              </div>
              <div class="space-y-2">
                <Label>Bed Type</Label>
                <Input v-model="form.bed_type" placeholder="King / Queen / Twin" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Base Rate (IDR)<span class="text-destructive">*</span></Label>
                <Input v-model.number="form.base_rate" type="number" min="0" required />
              </div>
              <div class="space-y-2">
                <Label>Area (m²)</Label>
                <Input v-model.number="form.area_sqm" type="number" step="0.01" min="0" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Description</Label>
              <Textarea v-model="form.description" rows="3" />
            </div>

            <div class="space-y-2">
              <Label>Amenities (comma separated)</Label>
              <Input v-model="amenitiesInput" placeholder="WiFi, AC, TV, Minibar" />
            </div>

            <div class="flex items-center justify-between gap-3">
              <label class="flex items-center gap-2 text-sm tracking-tight">
                <Checkbox v-model="form.breakfast_included" />
                <span>Breakfast included</span>
              </label>
              <label class="flex items-center gap-2 text-sm tracking-tight">
                <Checkbox v-model="form.is_active" />
                <span>Active</span>
              </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                {{ editingRoom ? "Save Changes" : "Create" }}
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Delete room type?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingRoom?.name }}" will be moved to trash.
          </p>
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
import { Textarea } from "@/components/ui/textarea";
import { computed, reactive, ref } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  hotelSlug: { type: String, required: true },
});

const baseUrl = computed(() => `/api/events/${props.eventId}/hotels/${props.hotelSlug}/room-types`);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("room_types.create"));
const canDelete = computed(() => hasPermission("room_types.delete"));

const { data, pending, refresh } = await useLazySanctumFetch(() => `${baseUrl.value}?per_page=50`, {
  key: () => `hotel-${props.eventId}-${props.hotelSlug}-rooms`,
});

const rooms = computed(() => data.value?.data ?? []);

const dialogOpen = ref(false);
const editingRoom = ref(null);
const saving = ref(false);
const amenitiesInput = ref("");

const form = reactive({
  name: "",
  max_pax: 2,
  bed_type: "",
  base_rate: 0,
  area_sqm: null,
  description: "",
  breakfast_included: true,
  is_active: true,
});

const resetForm = () => {
  Object.assign(form, {
    name: "",
    max_pax: 2,
    bed_type: "",
    base_rate: 0,
    area_sqm: null,
    description: "",
    breakfast_included: true,
    is_active: true,
  });
  amenitiesInput.value = "";
};

const openCreateDialog = () => {
  editingRoom.value = null;
  resetForm();
  dialogOpen.value = true;
};

const openEditDialog = (room) => {
  editingRoom.value = room;
  Object.assign(form, {
    name: room.name,
    max_pax: room.max_pax,
    bed_type: room.bed_type || "",
    base_rate: room.base_rate,
    area_sqm: room.area_sqm,
    description: room.description || "",
    breakfast_included: room.breakfast_included,
    is_active: room.is_active,
  });
  amenitiesInput.value = (room.amenities ?? []).join(", ");
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    const payload = {
      ...form,
      amenities: amenitiesInput.value
        ? amenitiesInput.value.split(",").map((s) => s.trim()).filter(Boolean)
        : [],
    };

    if (editingRoom.value) {
      await client(`${baseUrl.value}/${editingRoom.value.slug}`, { method: "PUT", body: payload });
      toast.success("Room type updated");
    } else {
      await client(baseUrl.value, { method: "POST", body: payload });
      toast.success("Room type created");
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
const deletingRoom = ref(null);
const deleting = ref(false);

const confirmDelete = (room) => {
  deletingRoom.value = room;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingRoom.value) return;
  deleting.value = true;
  try {
    await client(`${baseUrl.value}/${deletingRoom.value.slug}`, { method: "DELETE" });
    toast.success("Room type deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const formatRupiah = (n) => {
  return new Intl.NumberFormat("id-ID").format(Number(n) || 0);
};
</script>
