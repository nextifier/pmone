<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
              <Label for="name">Name<span class="text-destructive">*</span></Label>
              <Input id="name" v-model="form.name" placeholder="Grand Mercure Jakarta" required />
              <InputErrorMessage :errors="errors.name" />
            </div>
            <div class="space-y-2">
              <Label for="slug">Slug</Label>
              <Input id="slug" v-model="form.slug" placeholder="auto-generated from name" />
              <InputErrorMessage :errors="errors.slug" />
            </div>
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
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Location & Contact</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
              <Label for="city">City</Label>
              <Input id="city" v-model="form.city" placeholder="Jakarta" />
              <InputErrorMessage :errors="errors.city" />
            </div>
            <div class="space-y-2">
              <Label for="country">Country</Label>
              <Input id="country" v-model="form.country" placeholder="Indonesia" />
              <InputErrorMessage :errors="errors.country" />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="address">Address</Label>
            <Textarea id="address" v-model="form.address" rows="2" placeholder="Full address" />
            <InputErrorMessage :errors="errors.address" />
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
        <div class="frame-title">Commerce Settings</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-2">
              <Label for="check_in_time">Check-in Time</Label>
              <Input id="check_in_time" v-model="form.check_in_time" type="time" />
              <InputErrorMessage :errors="errors.check_in_time" />
            </div>
            <div class="space-y-2">
              <Label for="check_out_time">Check-out Time</Label>
              <Input id="check_out_time" v-model="form.check_out_time" type="time" />
              <InputErrorMessage :errors="errors.check_out_time" />
            </div>
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
              container-class="relative isolate aspect-3/2 max-w-md"
            />
            <InputErrorMessage :errors="errors.tmp_featured" />
          </div>

          <div class="space-y-2">
            <Label>Gallery</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Multiple images for hotel detail page. Up to 20 files, max 20MB each.
            </p>
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
import { reactive, ref, watch } from "vue";
import InputFile from "@/components/InputFile.vue";
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
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
  slug: "",
  description: "",
  address: "",
  city: "",
  country: "Indonesia",
  contact_email: "",
  contact_phone: "",
  commission_rate: 0,
  tax_percentage: 11,
  service_charge_percentage: 0,
  check_in_time: "14:00",
  check_out_time: "12:00",
  is_active: true,
});

const featuredFiles = ref([]);
const galleryFiles = ref([]);
const initialFeatured = ref(null);

watch(
  () => props.initial,
  (val) => {
    if (!val) return;
    Object.assign(form, {
      name: val.name ?? "",
      slug: val.slug ?? "",
      description: val.description ?? "",
      address: val.address ?? "",
      city: val.city ?? "",
      country: val.country ?? "Indonesia",
      contact_email: val.contact_email ?? "",
      contact_phone: val.contact_phone ?? "",
      commission_rate: val.commission_rate ?? 0,
      tax_percentage: val.tax_percentage ?? 11,
      service_charge_percentage: val.service_charge_percentage ?? 0,
      check_in_time: (val.check_in_time ?? "14:00:00").slice(0, 5),
      check_out_time: (val.check_out_time ?? "12:00:00").slice(0, 5),
      is_active: val.is_active ?? true,
    });
    initialFeatured.value = val.featured?.original ?? val.featured?.url ?? null;
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
