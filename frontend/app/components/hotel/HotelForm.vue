<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field :data-invalid="!!errors?.name">
            <FieldLabel for="name">Name</FieldLabel>
            <Input id="name" v-model="form.name" placeholder="Grand Mercure Jakarta" required :aria-invalid="!!errors?.name" />
            <FieldError :errors="errors.name" />
          </Field>

          <Field :data-invalid="!!errors?.description">
            <FieldLabel for="description">Description</FieldLabel>
            <Textarea
              :aria-invalid="!!errors?.description"
              id="description"
              v-model="form.description"
              rows="4"
              placeholder="Hotel description, facilities, ambience"
            />
            <FieldError :errors="errors.description" />
          </Field>

          <Field :data-invalid="!!errors?.star_rating">
            <FieldLabel for="star_rating">Star Rating</FieldLabel>
            <Select
              :model-value="form.star_rating ? String(form.star_rating) : 'none'"
              @update:model-value="(v) => (form.star_rating = v === 'none' ? null : Number(v))"
            >
              <SelectTrigger id="star_rating" class="w-full" :aria-invalid="!!errors?.star_rating">
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
            <FieldError :errors="errors.star_rating" />
          </Field>

          <Field :data-invalid="!!errors?.facilities">
            <FieldLabel>Facilities</FieldLabel>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Type and press Enter to add. Examples: WiFi, Swimming Pool, Gym, Restaurant.
            </p>
            <TagsInput v-model="form.facilities" class="text-sm" :aria-invalid="!!errors?.facilities">
              <TagsInputItem v-for="tag in form.facilities" :key="tag" :value="tag">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add facility" />
            </TagsInput>
            <FieldError :errors="errors.facilities" />
          </Field>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Location &amp; Contact</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <AddressFields v-model="form.address" :errors="errors" />

          <Field :data-invalid="!!errors?.google_maps_link">
            <FieldLabel for="google_maps_link">Google Maps Link</FieldLabel>
            <Input
              :aria-invalid="!!errors?.google_maps_link"
              id="google_maps_link"
              v-model="form.google_maps_link"
              type="url"
              placeholder="https://maps.app.goo.gl/..."
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Paste the share link from Google Maps. Used for the "Get Directions" button.
            </p>
            <FieldError :errors="errors.google_maps_link" />
          </Field>

          <Field :data-invalid="!!errors?.google_maps_embed_src">
            <FieldLabel for="google_maps_embed_src">Google Maps Embed Src</FieldLabel>
            <Textarea
              :aria-invalid="!!errors?.google_maps_embed_src"
              id="google_maps_embed_src"
              v-model="form.google_maps_embed_src"
              rows="3"
              placeholder="https://www.google.com/maps/embed?pb=..."
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              From Google Maps → Share → Embed a map, copy the <code>src</code> value of the iframe.
            </p>
            <FieldError :errors="errors.google_maps_embed_src" />
          </Field>

          <div class="grid gap-4 lg:grid-cols-2">
            <Field :data-invalid="!!errors?.contact_email">
              <FieldLabel for="contact_email">Contact Email</FieldLabel>
              <Input
                :aria-invalid="!!errors?.contact_email"
                id="contact_email"
                v-model="form.contact_email"
                type="email"
                placeholder="reservation@hotel.com"
              />
              <FieldError :errors="errors.contact_email" />
            </Field>
            <Field :data-invalid="!!errors?.contact_phone">
              <FieldLabel for="contact_phone">Contact Phone</FieldLabel>
              <InputPhone id="contact_phone" v-model="form.contact_phone" :aria-invalid="!!errors?.contact_phone" />
              <FieldError :errors="errors.contact_phone" />
            </Field>
          </div>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Policies</div>
      </div>
      <div class="frame-panel">
        <Field :data-invalid="!!errors?.cancellation_policy">
          <FieldLabel for="cancellation_policy">Cancellation Policy</FieldLabel>
          <Textarea
            :aria-invalid="!!errors?.cancellation_policy"
            id="cancellation_policy"
            v-model="form.cancellation_policy"
            rows="3"
            placeholder="Free cancellation up to 7 days before check-in"
          />
          <FieldError :errors="errors.cancellation_policy" />
        </Field>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Commerce Settings</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field :data-invalid="!!errors?.commission_rate">
            <FieldLabel for="commission_rate">Commission Rate (%)</FieldLabel>
            <InputGroup>
              <InputNumber
                :aria-invalid="!!errors?.commission_rate"
                id="commission_rate"
                v-model="form.commission_rate"
                :min="0"
                :max="100"
                decimal
                data-slot="input-group-control"
                class="cn-input-group-input flex-1"
              />
              <InputGroupAddon align="inline-end">
                <InputGroupText>%</InputGroupText>
              </InputGroupAddon>
            </InputGroup>
            <FieldError :errors="errors.commission_rate" />
          </Field>

          <div class="grid gap-4 lg:grid-cols-2">
            <Field :data-invalid="!!errors?.tax_percentage">
              <FieldLabel for="tax_percentage">Tax (%)</FieldLabel>
              <InputGroup>
                <InputNumber
                  :aria-invalid="!!errors?.tax_percentage"
                  id="tax_percentage"
                  v-model="form.tax_percentage"
                  :min="0"
                  :max="100"
                  decimal
                  data-slot="input-group-control"
                  class="cn-input-group-input flex-1"
                />
                <InputGroupAddon align="inline-end">
                  <InputGroupText>%</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <FieldError :errors="errors.tax_percentage" />
            </Field>
            <Field :data-invalid="!!errors?.service_charge_percentage">
              <FieldLabel for="service_charge_percentage">Service Charge (%)</FieldLabel>
              <InputGroup>
                <InputNumber
                  :aria-invalid="!!errors?.service_charge_percentage"
                  id="service_charge_percentage"
                  v-model="form.service_charge_percentage"
                  :min="0"
                  :max="100"
                  decimal
                  data-slot="input-group-control"
                  class="cn-input-group-input flex-1"
                />
                <InputGroupAddon align="inline-end">
                  <InputGroupText>%</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
              <FieldError :errors="errors.service_charge_percentage" />
            </Field>
          </div>

          <Field orientation="horizontal" :data-invalid="!!errors?.is_active">
            <FieldContent>
              <FieldLabel for="is_active" class="cursor-pointer">Hotel is active</FieldLabel>
              <p class="text-muted-foreground text-xs sm:text-sm">
                Inactive hotels are hidden from all event websites and cannot be booked by
                customers.
              </p>
              <FieldError :errors="errors.is_active" />
            </FieldContent>
            <Switch id="is_active" v-model="form.is_active" />
          </Field>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Images</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <Field :data-invalid="!!errors?.tmp_featured">
            <FieldLabel>Featured Image</FieldLabel>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Single hero image displayed on hotel cards. Format JPG/PNG/WebP.
            </p>
            <InputFileImage
              v-model="featuredFiles"
              :initial-image="initialFeatured"
              container-class="relative isolate aspect-video w-full max-w-2xl overflow-hidden rounded-lg"
              image-class="size-full object-cover"
            />
            <FieldError :errors="errors.tmp_featured" />
          </Field>

          <Field :data-invalid="!!errors?.gallery_files">
            <FieldLabel>Gallery</FieldLabel>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Multiple images for hotel detail page. Up to 20 files, max 20MB each.
            </p>

            <GalleryManager v-if="existingGallery.length" v-model:items="existingGallery" />

            <InputFile
              :aria-invalid="!!errors?.gallery_files"
              v-model="galleryFiles"
              allow-multiple
              :max-files="20"
              :max-file-size="'20MB'"
              :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']"
            />
            <FieldError :errors="errors.gallery_files" />
          </Field>
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
import { reactive, ref, watch } from "vue";
import AddressFields from "@/components/AddressFields.vue";
import InputFile from "@/components/InputFile.vue";
import InputFileImage from "@/components/InputFileImage.vue";
import GalleryManager from "@/components/GalleryManager.vue";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { InputPhone } from "@/components/ui/input-phone";
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
  address: {
    street: "",
    city: "",
    province: "",
    country: "Indonesia",
  },
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

watch(
  () => props.initial,
  (val) => {
    if (!val) return;
    Object.assign(form, {
      name: val.name ?? "",
      description: val.description ?? "",
      star_rating: val.star_rating ?? null,
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
    form.address = {
      street: val.address ?? "",
      city: val.city ?? "",
      province: val.province ?? "",
      country: val.country ?? "Indonesia",
    };
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
