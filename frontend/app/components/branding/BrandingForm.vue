<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <div class="space-y-4">
      <div class="grid gap-x-2 gap-y-4 lg:grid-cols-[minmax(0,200px)_1fr]">
        <div class="space-y-2">
          <Label>Logo</Label>
          <InputFileImage
            v-model="logoFiles"
            v-model:delete-flag="deleteLogo"
            :initial-image="form.logo_url"
            :accepted-file-types="logoMimeTypes"
            container-class="relative isolate aspect-3/2 w-full"
            image-class="border-border bg-background size-full rounded-lg border object-contain p-2"
          />
          <p class="text-muted-foreground text-xs tracking-tight text-nowrap sm:text-sm">
            PNG, JPG, WebP or SVG. Max 5MB.
          </p>
        </div>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Company Name</Label>
            <Input v-model="form.company_name" placeholder="PT PM One" required />
          </div>
          <div class="space-y-2">
            <Label>Tax ID (NPWP)</Label>
            <Input v-model="form.tax_id" placeholder="00.000.000.0-000.000" />
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <Label>Address</Label>
        <Textarea
          v-model="form.address"
          rows="2"
          placeholder="Street, city, postal code"
        />
      </div>

      <div class="grid gap-x-2 gap-y-4 lg:grid-cols-3">
        <div class="space-y-2">
          <Label>Phone</Label>
          <InputPhone v-model="form.phone" />
        </div>
        <div class="space-y-2">
          <Label>Email</Label>
          <Input v-model="form.email" type="email" placeholder="info@pmone.id" />
        </div>
        <div class="space-y-2">
          <Label>Website</Label>
          <InputLink v-model="form.website" />
        </div>
      </div>

      <div class="space-y-2">
        <Label>Footer Note</Label>
        <Textarea
          v-model="form.footer_note"
          rows="2"
          placeholder="Thank you for your business."
        />
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Appears at the bottom of every Invoice & Receipt PDF.
        </p>
      </div>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
      <Button
        v-if="$slots.cancel || cancelLabel"
        variant="outline"
        type="button"
        @click="$emit('cancel')"
      >
        {{ cancelLabel || "Cancel" }}
      </Button>
      <Button type="submit" :disabled="saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, watch } from "vue";
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputLink } from "@/components/ui/input-link";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  submitLabel: { type: String, default: "Save Branding" },
  cancelLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "submit", "cancel"]);

const logoFiles = ref([]);
const deleteLogo = ref(false);

const logoMimeTypes = [
  "image/jpeg",
  "image/png",
  "image/jpg",
  "image/webp",
  "image/svg+xml",
];

const blank = () => ({
  logo_url: "",
  company_name: "",
  address: "",
  phone: "",
  email: "",
  website: "",
  tax_id: "",
  footer_note: "",
});

const form = reactive(blank());

watch(
  () => props.modelValue,
  (val) => {
    Object.assign(form, { ...blank(), ...(val || {}) });
    logoFiles.value = [];
    deleteLogo.value = false;
  },
  { immediate: true, deep: true }
);

const handleSubmit = () => {
  const payload = JSON.parse(JSON.stringify(form));
  const tmp = logoFiles.value?.[0];
  payload.tmp_logo = typeof tmp === "string" && tmp.startsWith("tmp-") ? tmp : null;
  payload.delete_logo = !!deleteLogo.value;
  emit("update:modelValue", payload);
  emit("submit", payload);
};
</script>
