<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="space-y-2">
        <Label>Name<span class="text-destructive">*</span></Label>
        <Input v-model="form.name" placeholder="Grand Mercure Jakarta" required />
      </div>
      <div class="space-y-2">
        <Label>Slug</Label>
        <Input v-model="form.slug" placeholder="auto-generated from name" />
      </div>
    </div>

    <div class="space-y-2">
      <Label>Description</Label>
      <Textarea v-model="form.description" rows="4" placeholder="Hotel description, facilities, ambience..." />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="space-y-2">
        <Label>City</Label>
        <Input v-model="form.city" placeholder="Jakarta" />
      </div>
      <div class="space-y-2">
        <Label>Country</Label>
        <Input v-model="form.country" placeholder="Indonesia" />
      </div>
    </div>

    <div class="space-y-2">
      <Label>Address</Label>
      <Textarea v-model="form.address" rows="2" placeholder="Full address" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="space-y-2">
        <Label>Contact Email</Label>
        <Input v-model="form.contact_email" type="email" placeholder="reservation@hotel.com" />
      </div>
      <div class="space-y-2">
        <Label>Contact Phone</Label>
        <Input v-model="form.contact_phone" placeholder="+62 21 ..." />
      </div>
      <div class="space-y-2">
        <Label>Commission Rate (%)</Label>
        <Input v-model.number="form.commission_rate" type="number" step="0.01" min="0" max="100" />
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="space-y-2">
        <Label>Check-in Time</Label>
        <Input v-model="form.check_in_time" type="time" />
      </div>
      <div class="space-y-2">
        <Label>Check-out Time</Label>
        <Input v-model="form.check_out_time" type="time" />
      </div>
      <div class="space-y-2">
        <Label>Tax (%)</Label>
        <Input v-model.number="form.tax_percentage" type="number" step="0.01" min="0" max="100" />
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="space-y-2">
        <Label>Service Charge (%)</Label>
        <Input v-model.number="form.service_charge_percentage" type="number" step="0.01" min="0" max="100" />
      </div>
      <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm tracking-tight">
          <Checkbox v-model="form.is_active" />
          <span>Active</span>
        </label>
      </div>
    </div>

    <div class="space-y-2">
      <Label>Featured Image</Label>
      <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
        Single hero image displayed on hotel cards. Format JPG/PNG/WebP.
      </p>
      <InputFileImage v-model="featuredFiles" :initial-image="initialFeatured" container-class="relative isolate aspect-3/2 max-w-md" />
    </div>

    <div class="space-y-2">
      <Label>Gallery</Label>
      <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
        Multiple images for hotel detail page. Up to 20 files, max 20MB each.
      </p>
      <InputFile v-model="galleryFiles" allow-multiple :max-files="20" :max-file-size="'20MB'" :accepted-file-types="['image/jpeg', 'image/png', 'image/webp']" />
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
      <Button variant="outline" type="button" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
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
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  initial: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
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
  { immediate: true, deep: true }
);

const handleSubmit = () => {
  const payload = { ...form };
  const featured = featuredFiles.value?.[0];
  if (featured && featured.startsWith("tmp-")) {
    payload.tmp_featured = featured;
  }
  if (Array.isArray(galleryFiles.value) && galleryFiles.value.length > 0) {
    payload.gallery_files = galleryFiles.value.filter((f) => typeof f === "string" && f.startsWith("tmp-"));
  }
  emit("submit", payload);
};
</script>
