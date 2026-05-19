<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" placeholder="Grand Mercure Jakarta" required />
            <InputErrorMessage :errors="errors.name" />
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              v-model="form.description"
              rows="4"
              placeholder="Hotel description, facilities, ambience..."
            />
            <InputErrorMessage :errors="errors.description" />
          </div>

          <div class="space-y-2">
            <Label for="star_rating">Star Rating</Label>
            <Select
              :model-value="form.star_rating ? String(form.star_rating) : 'none'"
              @update:model-value="(v) => (form.star_rating = v === 'none' ? null : Number(v))"
            >
              <SelectTrigger id="star_rating" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">No rating</SelectItem>
                <SelectItem value="1">1 Star</SelectItem>
                <SelectItem value="2">2 Stars</SelectItem>
                <SelectItem value="3">3 Stars</SelectItem>
                <SelectItem value="4">4 Stars</SelectItem>
                <SelectItem value="5">5 Stars</SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.star_rating" />
          </div>

          <div class="space-y-2">
            <Label>Facilities</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Type and press Enter to add. Examples: WiFi, Swimming Pool, Gym, Restaurant.
            </p>
            <TagsInput v-model="form.facilities" class="text-sm">
              <TagsInputItem v-for="tag in form.facilities" :key="tag" :value="tag">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add facility..." />
            </TagsInput>
            <InputErrorMessage :errors="errors.facilities" />
          </div>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Location &amp; Contact</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
              <Label>Country</Label>
              <LocationCombobox
                v-model="form.country"
                :options="countries"
                :pinned="['Indonesia']"
                placeholder="Select country"
              />
              <InputErrorMessage :errors="errors.country" />
            </div>
            <div class="space-y-2">
              <Label>City</Label>
              <LocationCombobox
                v-model="form.city"
                :options="cityOptions"
                :pinned="['Jakarta']"
                :disabled="!isIndonesia && !form.country"
                placeholder="Select city"
              />
              <InputErrorMessage :errors="errors.city" />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="address">Address</Label>
            <Textarea id="address" v-model="form.address" rows="2" placeholder="Full address" />
            <InputErrorMessage :errors="errors.address" />
          </div>

          <div class="space-y-2">
            <Label for="google_maps_link">Google Maps Link</Label>
            <Input
              id="google_maps_link"
              v-model="form.google_maps_link"
              type="url"
              placeholder="https://maps.app.goo.gl/..."
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Paste the share link from Google Maps. Used for the "Get Directions" button.
            </p>
            <InputErrorMessage :errors="errors.google_maps_link" />
          </div>

          <div class="space-y-2">
            <Label for="google_maps_embed_src">Google Maps Embed Src</Label>
            <Textarea
              id="google_maps_embed_src"
              v-model="form.google_maps_embed_src"
              rows="3"
              placeholder="https://www.google.com/maps/embed?pb=..."
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              From Google Maps → Share → Embed a map, copy the <code>src</code> value of the iframe.
            </p>
            <InputErrorMessage :errors="errors.google_maps_embed_src" />
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
              <Label for="contact_email">Contact Email</Label>
              <Input
                id="contact_email"
                v-model="form.contact_email"
                type="email"
                placeholder="reservation@hotel.com"
              />
              <InputErrorMessage :errors="errors.contact_email" />
            </div>
            <div class="space-y-2">
              <Label for="contact_phone">Contact Phone</Label>
              <InputPhone id="contact_phone" v-model="form.contact_phone" />
              <InputErrorMessage :errors="errors.contact_phone" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Policies</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label for="cancellation_policy">Cancellation Policy</Label>
          <Textarea
            id="cancellation_policy"
            v-model="form.cancellation_policy"
            rows="3"
            placeholder="Free cancellation up to 7 days before check-in..."
          />
          <InputErrorMessage :errors="errors.cancellation_policy" />
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Commerce Settings</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="commission_rate">Commission Rate (%)</Label>
            <Input
              id="commission_rate"
              v-model.number="form.commission_rate"
              type="number"
              step="0.01"
              min="0"
              max="100"
            />
            <InputErrorMessage :errors="errors.commission_rate" />
          </div>

          <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
              <Label for="tax_percentage">Tax (%)</Label>
              <Input
                id="tax_percentage"
                v-model.number="form.tax_percentage"
                type="number"
                step="0.01"
                min="0"
                max="100"
              />
              <InputErrorMessage :errors="errors.tax_percentage" />
            </div>
            <div class="space-y-2">
              <Label for="service_charge_percentage">Service Charge (%)</Label>
              <Input
                id="service_charge_percentage"
                v-model.number="form.service_charge_percentage"
                type="number"
                step="0.01"
                min="0"
                max="100"
              />
              <InputErrorMessage :errors="errors.service_charge_percentage" />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox id="is_active" v-model="form.is_active" />
            <Label for="is_active" class="cursor-pointer font-normal">Active</Label>
          </div>
          <InputErrorMessage :errors="errors.is_active" />
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Images</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label>Featured Image</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Single hero image displayed on hotel cards. Format JPG/PNG/WebP.
            </p>
            <InputFileImage
              v-model="featuredFiles"
              :initial-image="initialFeatured"
              container-class="relative isolate aspect-video w-full max-w-2xl overflow-hidden rounded-lg"
              image-class="size-full object-cover"
            />
            <InputErrorMessage :errors="errors.tmp_featured" />
          </div>

          <div class="space-y-2">
            <Label>Gallery</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Multiple images for hotel detail page. Up to 20 files, max 20MB each.
            </p>

            <div
              v-if="existingGallery.length"
              class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5"
            >
              <div
                v-for="item in existingGallery"
                :key="item.id"
                class="bg-muted relative aspect-square overflow-hidden rounded-lg border"
              >
                <img
                  :src="item.sm || item.md || item.url"
                  :alt="item.name || 'Gallery'"
                  loading="lazy"
                  class="size-full object-cover"
                />
                <button
                  type="button"
                  :disabled="deletingMediaId === item.id"
                  class="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-black/55 text-white shadow-sm ring ring-white/20 backdrop-blur-sm transition hover:bg-black disabled:opacity-50"
                  @click="removeGalleryItem(item)"
                >
                  <Spinner v-if="deletingMediaId === item.id" class="size-3.5" />
                  <Icon v-else name="hugeicons:delete-01" class="size-3.5" />
                </button>
              </div>
            </div>

            <InputFile
              v-model="galleryFiles"
              allow-multiple
              :max-files="20"
              :max-file-size="'20MB'"
              :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
            />
            <InputErrorMessage :errors="errors.gallery_files" />
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button variant="outline" type="button" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="saving">
        <Spinner v-if="saving" />
        {{ saving ? "Saving..." : submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import InputFile from "@/components/InputFile.vue";
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { LocationCombobox } from "@/components/ui/location-combobox";
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
import countries from "@/data/countries.json";
import indonesiaCities from "@/data/indonesia-cities.json";

const props = defineProps({
  initial: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: "Save Hotel" },
});

const emit = defineEmits(["submit", "cancel"]);

const form = reactive({
  name: "",
  description: "",
  star_rating: null,
  address: "",
  city: "",
  country: "Indonesia",
  google_maps_link: "",
  google_maps_embed_src: "",
  facilities: [],
  contact_email: "",
  contact_phone: "",
  cancellation_policy: "",
  commission_rate: 0,
  tax_percentage: 11,
  service_charge_percentage: 0,
  is_active: true,
});

const featuredFiles = ref([]);
const galleryFiles = ref([]);
const initialFeatured = ref(null);
const existingGallery = ref([]);
const deletingMediaId = ref(null);

const client = useSanctumClient();

const removeGalleryItem = async (item) => {
  if (!item?.id) return;
  deletingMediaId.value = item.id;
  try {
    await client(`/api/media/${item.id}`, { method: "DELETE" });
    existingGallery.value = existingGallery.value.filter((g) => g.id !== item.id);
  } catch (err) {
    const { toast } = await import("vue-sonner");
    toast.error("Failed to remove image", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deletingMediaId.value = null;
  }
};

const isIndonesia = computed(() => form.country === "Indonesia");

const cityOptions = computed(() => {
  if (!isIndonesia.value) return [];
  const labels = (indonesiaCities ?? [])
    .map((c) => (typeof c === "string" ? c : c?.label))
    .filter(Boolean);
  return Array.from(new Set(labels)).sort();
});

watch(
  () => props.initial,
  (val) => {
    if (!val) return;
    Object.assign(form, {
      name: val.name ?? "",
      description: val.description ?? "",
      star_rating: val.star_rating ?? null,
      address: val.address ?? "",
      city: val.city ?? "",
      country: val.country ?? "Indonesia",
      google_maps_link: val.google_maps_link ?? "",
      google_maps_embed_src: val.google_maps_embed_src ?? "",
      facilities: Array.isArray(val.facilities) ? [...val.facilities] : [],
      contact_email: val.contact_email ?? "",
      contact_phone: val.contact_phone ?? "",
      cancellation_policy: val.cancellation_policy ?? "",
      commission_rate: val.commission_rate ?? 0,
      tax_percentage: val.tax_percentage ?? 11,
      service_charge_percentage: val.service_charge_percentage ?? 0,
      is_active: val.is_active ?? true,
    });
    initialFeatured.value = val.featured?.original ?? val.featured?.url ?? null;
    existingGallery.value = Array.isArray(val.gallery) ? [...val.gallery] : [];
  },
  { immediate: true, deep: true },
);

const handleSubmit = () => {
  const payload = { ...form };
  const featured = featuredFiles.value?.[0];
  if (featured && featured.startsWith("tmp-")) {
    payload.tmp_featured = featured;
  }
  if (Array.isArray(galleryFiles.value) && galleryFiles.value.length > 0) {
    payload.gallery_files = galleryFiles.value.filter(
      (f) => typeof f === "string" && f.startsWith("tmp-"),
    );
  }
  emit("submit", payload);
};
</script>
