<template>
  <form @submit.prevent="handleSubmit" class="mt-4 space-y-3">
    <div class="space-y-2">
      <Label>Full Name<span class="text-destructive">*</span></Label>
      <Input v-model="form.name" required />
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <Label>Email<span class="text-destructive">*</span></Label>
        <Input v-model="form.email" type="email" required />
      </div>
      <div class="space-y-2">
        <Label>Phone<span class="text-destructive">*</span></Label>
        <Input v-model="form.phone" required />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <Label>ID Type<span class="text-destructive">*</span></Label>
        <select v-model="form.identity_type" required class="border-input w-full rounded-md border px-3 py-2 text-sm tracking-tight">
          <option value="nik">NIK (KTP)</option>
          <option value="passport">Passport</option>
        </select>
      </div>
      <div class="space-y-2">
        <Label>ID Number<span class="text-destructive">*</span></Label>
        <Input v-model="form.identity_number" required />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <Label>Nationality</Label>
        <Input v-model="form.nationality" placeholder="Indonesia" />
      </div>
      <div class="space-y-2">
        <Label>Company</Label>
        <Input v-model="form.company" />
      </div>
    </div>
    <div class="space-y-2">
      <Label>Address</Label>
      <Textarea v-model="form.address" rows="2" />
    </div>
    <div class="space-y-2">
      <Label>Special Request</Label>
      <Textarea v-model="form.special_request" rows="2" placeholder="Late check-in, dietary, etc." />
    </div>

    <TermsCheckbox v-model="acceptTerms" />

    <div class="flex justify-end gap-2 pt-3">
      <Button type="button" variant="outline" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="!acceptTerms || saving">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        Confirm & Pay
      </Button>
    </div>
  </form>
</template>

<script setup>
import TermsCheckbox from "./TermsCheckbox.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { reactive, ref } from "vue";

const props = defineProps({
  saving: { type: Boolean, default: false },
});

const emit = defineEmits(["submit", "cancel"]);

const form = reactive({
  name: "",
  email: "",
  phone: "",
  identity_type: "nik",
  identity_number: "",
  nationality: "Indonesia",
  company: "",
  address: "",
  special_request: "",
});

const acceptTerms = ref(false);

const handleSubmit = () => {
  emit("submit", {
    guest_name: form.name,
    guest_email: form.email,
    guest_phone: form.phone,
    guest_identity_type: form.identity_type,
    guest_identity_number: form.identity_number,
    guest_nationality: form.nationality,
    guest_company: form.company,
    guest_address: form.address,
    special_request: form.special_request,
    accept_terms: acceptTerms.value,
  });
};
</script>
