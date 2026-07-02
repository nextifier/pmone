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

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <div
        v-for="room in rooms"
        :key="room.id"
        class="group border-border bg-card hover:border-primary/40 flex items-start gap-x-3 rounded-xl border p-3 transition-colors sm:gap-x-4 sm:p-4"
      >
        <div
          class="bg-muted border-border relative aspect-4/5 w-24 shrink-0 overflow-hidden rounded-lg border sm:w-32"
        >
          <Lightbox
            v-if="room.gallery?.length"
            :items="room.gallery"
            :alt="room.name"
            thumbnail-key="md"
          >
            <template #trigger="{ open }">
              <button
                type="button"
                class="block size-full cursor-zoom-in"
                @click="open"
              >
                <img
                  :src="room.gallery[0].md || room.gallery[0].lg || room.gallery[0].url"
                  :alt="room.name"
                  class="size-full object-cover"
                  loading="lazy"
                  decoding="async"
                />
              </button>
            </template>
          </Lightbox>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-y-1.5">
          <div class="flex flex-wrap items-center gap-1.5">
            <span
              v-if="room.is_active"
              class="border-border text-muted-foreground rounded-full border px-2 py-0.5 text-xs font-medium tracking-tight"
            >
              Active
            </span>
            <span
              v-else
              class="border-border text-muted-foreground bg-muted rounded-full border px-2 py-0.5 text-xs font-medium tracking-tight"
            >
              Inactive
            </span>
            <span
              v-if="room.breakfast_included"
              class="text-primary border-primary/30 bg-primary/5 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium tracking-tight"
            >
              <Icon name="hugeicons:coffee-02" class="size-3" />
              Breakfast
            </span>
          </div>

          <h3 class="text-base font-semibold tracking-tighter sm:text-lg">
            {{ room.name }}
          </h3>

          <div class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tracking-tight sm:text-sm">
            <span v-if="room.bed_type" class="inline-flex items-center gap-1">
              <Icon name="hugeicons:bed-single-01" class="size-3.5" />
              {{ room.bed_type }}
            </span>
            <span class="inline-flex items-center gap-1">
              <Icon name="hugeicons:user-multiple" class="size-3.5" />
              {{ room.max_pax }} pax
            </span>
            <span v-if="room.area_sqm" class="inline-flex items-center gap-1">
              <Icon name="hugeicons:square-arrow-expand-02" class="size-3.5" />
              {{ room.area_sqm }} m²
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <p class="text-sm tracking-tight">
              <template v-if="room.pricing_type === 'dynamic' && room.pricing_periods?.length">
                <span class="text-muted-foreground">from </span>
                <span class="font-semibold">Rp{{ formatRupiah(minPeriodRate(room)) }}</span>
              </template>
              <template v-else>
                <span class="font-semibold">Rp{{ formatRupiah(room.base_rate) }}</span>
              </template>
              <span class="text-muted-foreground"> / night</span>
            </p>
            <span
              v-if="room.pricing_type === 'dynamic'"
              class="text-info border-info/30 bg-info/10 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium tracking-tight"
            >
              Dynamic
            </span>
          </div>

          <div v-if="room.amenities?.length" class="flex flex-wrap gap-1 pt-1">
            <span
              v-for="amenity in room.amenities.slice(0, 3)"
              :key="amenity"
              class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs tracking-tight"
            >
              {{ amenity }}
            </span>
            <span
              v-if="room.amenities.length > 3"
              class="text-muted-foreground rounded-full px-2 py-0.5 text-xs tracking-tight"
            >
              +{{ room.amenities.length - 3 }} more
            </span>
          </div>

          <div class="mt-auto flex items-center justify-end gap-1 pt-2">
            <Button
              variant="ghost"
              size="iconSm"
              title="Edit"
              @click="openEditDialog(room)"
            >
              <Icon name="hugeicons:edit-02" class="size-4" />
            </Button>
            <Button
              v-if="canDelete"
              variant="ghost"
              size="iconSm"
              class="hover:bg-destructive/10 text-destructive"
              title="Delete"
              @click="confirmDelete(room)"
            >
              <Icon name="hugeicons:delete-02" class="size-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <DialogResponsive v-model:open="dialogOpen" dialog-max-width="32rem" :overflow-content="true">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">{{ editingRoom ? "Edit Room Type" : "Add Room Type" }}</h3>

          <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" required placeholder="Deluxe King" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Max Pax</Label>
                <InputNumber v-model="form.max_pax" :min="1" required />
              </div>
              <div class="space-y-2">
                <Label>Bed Type</Label>
                <Input v-model="form.bed_type" placeholder="King / Queen / Twin" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-2">
                <Label>Base Rate (IDR)</Label>
                <InputGroup>
                  <InputNumber
                    v-model="form.base_rate"
                    :min="0"
                    required
                    data-slot="input-group-control"
                    class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                  />
                  <InputGroupAddon>
                    <InputGroupText>Rp</InputGroupText>
                  </InputGroupAddon>
                </InputGroup>
              </div>
              <div class="space-y-2">
                <Label>Area (m²)</Label>
                <InputNumber v-model="form.area_sqm" :min="0" decimal />
              </div>
            </div>

            <div class="space-y-2">
              <Label>Pricing Mode</Label>
              <Select :model-value="form.pricing_type" @update:model-value="(v) => (form.pricing_type = v || 'flat')">
                <SelectTrigger class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="flat">Flat Rate (same price every night)</SelectItem>
                  <SelectItem value="dynamic">Dynamic (per date range)</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.pricing_type === 'dynamic'" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                Base rate above is used as fallback display. Booking total uses period rates below.
              </p>
            </div>

            <div v-if="form.pricing_type === 'dynamic'" class="space-y-2">
              <div class="flex items-center justify-between">
                <Label>Pricing Periods</Label>
                <Button size="sm" type="button" variant="outline" @click="addPricingPeriod">
                  <Icon name="lucide:plus" class="-ml-1 size-3.5 shrink-0" />
                  Add Period
                </Button>
              </div>
              <p v-if="form.pricing_periods.length === 0" class="text-muted-foreground text-sm tracking-tight rounded-md border border-dashed py-6 text-center">
                No pricing periods yet. Add one to define a date range and rate.
              </p>
              <div v-if="pricingPeriodOverlap" class="text-destructive text-xs sm:text-sm tracking-tight">
                {{ pricingPeriodOverlap }}
              </div>
              <div
                v-for="(period, idx) in form.pricing_periods"
                :key="idx"
                class="border-border rounded-md border p-3 space-y-2"
              >
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1">
                    <Label class="text-xs sm:text-sm">Start Date</Label>
                    <DatePicker
                      :model-value="parseLocalDateString(period.start_date)"
                      placeholder="Pick start date"
                      @update:model-value="
                        (d) => (period.start_date = d ? toLocalDateString(d) : '')
                      "
                    />
                  </div>
                  <div class="space-y-1">
                    <Label class="text-xs sm:text-sm">End Date</Label>
                    <DatePicker
                      :model-value="parseLocalDateString(period.end_date)"
                      placeholder="Pick end date"
                      :min="parseLocalDateString(period.start_date)"
                      @update:model-value="
                        (d) => (period.end_date = d ? toLocalDateString(d) : '')
                      "
                    />
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1">
                    <Label class="text-xs sm:text-sm">Rate (IDR)</Label>
                    <InputGroup>
                      <InputNumber
                        v-model="period.rate"
                        :min="0"
                        required
                        data-slot="input-group-control"
                        class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
                      />
                      <InputGroupAddon>
                        <InputGroupText>Rp</InputGroupText>
                      </InputGroupAddon>
                    </InputGroup>
                  </div>
                  <div class="space-y-1">
                    <Label class="text-xs sm:text-sm">Label (optional)</Label>
                    <Input v-model="period.label" placeholder="e.g. Weekend, Peak" />
                  </div>
                </div>
                <div class="flex items-center justify-between pt-1">
                  <div class="flex items-center gap-2">
                    <Switch :id="`period-active-${idx}`" v-model="period.is_active" />
                    <Label :for="`period-active-${idx}`" class="cursor-pointer">Active</Label>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="iconSm"
                    class="hover:bg-destructive/10 text-destructive"
                    title="Remove period"
                    @click="removePricingPeriod(idx)"
                  >
                    <Icon name="hugeicons:delete-02" class="size-3.5" />
                  </Button>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <Label>Description</Label>
              <Textarea v-model="form.description" rows="3" />
            </div>

            <div class="space-y-2">
              <Label>Cancellation Policy</Label>
              <Textarea v-model="form.cancellation_policy" rows="2" placeholder="Free cancellation up to X days..." />
            </div>

            <div class="space-y-2">
              <Label>Room Facilities</Label>
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                Type and press Enter. Examples: WiFi, AC, TV, Minibar, Bathtub, Safe.
              </p>
              <TagsInput v-model="form.amenities" class="text-sm">
                <TagsInputItem v-for="tag in form.amenities" :key="tag" :value="tag">
                  <TagsInputItemText />
                  <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="Add facility..." />
              </TagsInput>
            </div>

            <div class="space-y-2">
              <Label>Room Gallery</Label>
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                Up to 20 images. JPG/PNG/WebP, max 20MB each.
              </p>
              <InputFile
                v-model="galleryFiles"
                allow-multiple
                :max-files="20"
                :max-file-size="'20MB'"
                :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
              />
              <GalleryManager
                v-if="editGallery.length"
                v-model:items="editGallery"
                :can-delete="canDelete"
                :alt="editingRoom?.name || 'Room'"
                class="pt-2"
                @changed="refresh"
              />
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-3">
              <div class="flex items-center gap-2">
                <Switch id="room-breakfast" v-model="form.breakfast_included" />
                <Label for="room-breakfast" class="cursor-pointer">Breakfast included</Label>
              </div>
              <div class="flex items-center gap-2">
                <Switch id="room-smoking" v-model="form.smoking_allowed" />
                <Label for="room-smoking" class="cursor-pointer">Smoking allowed</Label>
              </div>
              <div class="flex items-center gap-2">
                <Switch id="room-active" v-model="form.is_active" />
                <Label for="room-active" class="cursor-pointer">Active</Label>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button variant="outline" type="button" @click="dialogOpen = false">Cancel</Button>
              <Button type="submit" :disabled="saving">
                <Spinner v-if="saving" />
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
          <div class="text-foreground text-lg font-semibold tracking-tight">Delete room type?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingRoom?.name }}" will be moved to trash.
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
import { Lightbox } from "@/components/ui/lightbox";
import InputFile from "@/components/InputFile.vue";
import GalleryManager from "@/components/GalleryManager.vue";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { DatePicker } from "@/components/ui/date-picker";
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
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { parseLocalDateString, toLocalDateString } from "@/lib/utils";
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
const galleryFiles = ref([]);
const editGallery = ref([]);

const form = reactive({
  name: "",
  max_pax: 2,
  bed_type: "",
  base_rate: 0,
  area_sqm: null,
  description: "",
  amenities: [],
  cancellation_policy: "",
  breakfast_included: true,
  smoking_allowed: false,
  is_active: true,
  pricing_type: "flat",
  pricing_periods: [],
});

const resetForm = () => {
  Object.assign(form, {
    name: "",
    max_pax: 2,
    bed_type: "",
    base_rate: 0,
    area_sqm: null,
    description: "",
    amenities: [],
    cancellation_policy: "",
    breakfast_included: true,
    smoking_allowed: false,
    is_active: true,
    pricing_type: "flat",
    pricing_periods: [],
  });
  galleryFiles.value = [];
  editGallery.value = [];
};

const addPricingPeriod = () => {
  form.pricing_periods.push({
    id: null,
    start_date: "",
    end_date: "",
    rate: 0,
    label: "",
    is_active: true,
  });
};

const removePricingPeriod = (idx) => {
  form.pricing_periods.splice(idx, 1);
};

const pricingPeriodOverlap = computed(() => {
  const periods = form.pricing_periods
    .filter((p) => p.start_date && p.end_date)
    .slice()
    .sort((a, b) => a.start_date.localeCompare(b.start_date));

  for (let i = 0; i < periods.length - 1; i++) {
    if (periods[i].end_date >= periods[i + 1].start_date) {
      return `Periods overlap: ${periods[i].start_date} - ${periods[i].end_date} and ${periods[i + 1].start_date} - ${periods[i + 1].end_date}.`;
    }
  }
  return null;
});

const minPeriodRate = (room) => {
  if (!room.pricing_periods?.length) return room.base_rate;
  return Math.min(...room.pricing_periods.map((p) => Number(p.rate) || 0));
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
    amenities: Array.isArray(room.amenities) ? [...room.amenities] : [],
    cancellation_policy: room.cancellation_policy || "",
    breakfast_included: room.breakfast_included,
    smoking_allowed: room.smoking_allowed ?? false,
    is_active: room.is_active,
    pricing_type: room.pricing_type || "flat",
    pricing_periods: Array.isArray(room.pricing_periods)
      ? room.pricing_periods.map((p) => ({
          id: p.id,
          start_date: p.start_date,
          end_date: p.end_date,
          rate: Number(p.rate),
          label: p.label || "",
          is_active: p.is_active ?? true,
        }))
      : [],
  });
  galleryFiles.value = [];
  editGallery.value = Array.isArray(room.gallery) ? room.gallery.map((image) => ({ ...image })) : [];
  dialogOpen.value = true;
};

const handleSubmit = async () => {
  if (form.pricing_type === "dynamic" && pricingPeriodOverlap.value) {
    toast.error("Pricing periods overlap. Fix before saving.");
    return;
  }
  if (form.pricing_type === "dynamic" && form.pricing_periods.length === 0) {
    toast.error("Add at least one pricing period for dynamic pricing.");
    return;
  }

  saving.value = true;
  try {
    const payload = { ...form };

    payload.pricing_periods = form.pricing_periods
      .filter((p) => p.start_date && p.end_date && Number(p.rate) > 0)
      .map((p) => ({
        id: p.id || undefined,
        start_date: p.start_date,
        end_date: p.end_date,
        rate: Number(p.rate),
        label: p.label || null,
        is_active: p.is_active,
      }));

    if (Array.isArray(galleryFiles.value) && galleryFiles.value.length > 0) {
      payload.gallery_files = galleryFiles.value.filter(
        (f) => typeof f === "string" && f.startsWith("tmp-"),
      );
    }

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
