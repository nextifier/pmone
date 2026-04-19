<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Guest Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="guest_name">Full Name<span class="text-destructive">*</span></Label>
            <Input id="guest_name" v-model="form.name" required />
            <InputErrorMessage :errors="errors.guest_name" />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="guest_email">Email<span class="text-destructive">*</span></Label>
              <Input id="guest_email" v-model="form.email" type="email" required />
              <InputErrorMessage :errors="errors.guest_email" />
            </div>
            <div class="space-y-2">
              <Label for="guest_phone">Phone<span class="text-destructive">*</span></Label>
              <InputPhone id="guest_phone" v-model="form.phone" />
              <InputErrorMessage :errors="errors.guest_phone" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="identity_type">ID Type<span class="text-destructive">*</span></Label>
              <Select v-model="form.identity_type">
                <SelectTrigger id="identity_type" class="w-full">
                  <SelectValue placeholder="Select ID type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="nik">NIK (KTP)</SelectItem>
                  <SelectItem value="passport">Passport</SelectItem>
                </SelectContent>
              </Select>
              <InputErrorMessage :errors="errors.guest_identity_type" />
            </div>
            <div class="space-y-2">
              <Label for="identity_number">ID Number<span class="text-destructive">*</span></Label>
              <Input id="identity_number" v-model="form.identity_number" required />
              <InputErrorMessage :errors="errors.guest_identity_number" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="nationality">Nationality</Label>
              <Input id="nationality" v-model="form.nationality" placeholder="Indonesia" />
              <InputErrorMessage :errors="errors.guest_nationality" />
            </div>
            <div class="space-y-2">
              <Label for="company">Company</Label>
              <Input id="company" v-model="form.company" />
              <InputErrorMessage :errors="errors.guest_company" />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="special_request">Special Request</Label>
            <Textarea
              id="special_request"
              v-model="form.special_request"
              rows="3"
              placeholder="Late check-in, dietary, etc."
            />
            <InputErrorMessage :errors="errors.special_request" />
          </div>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-panel">
        <TermsCheckbox v-model="accept" />
        <InputErrorMessage class="mt-2" :errors="errors.accept_terms" />
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="!accept || saving">
        <Spinner v-if="saving" />
        {{ saving ? "Processing..." : "Confirm & Pay" }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import TermsCheckbox from "./TermsCheckbox.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { InputPhone } from "@/components/ui/input-phone";
import { nextTick, reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({}),
  },
  acceptTerms: {
    type: Boolean,
    default: false,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits([
  "update:modelValue",
  "update:acceptTerms",
  "submit",
  "cancel",
]);

const createDefault = () => ({
  name: "",
  email: "",
  phone: "",
  identity_type: "nik",
  identity_number: "",
  nationality: "Indonesia",
  company: "",
  special_request: "",
});

const form = reactive({ ...createDefault(), ...(props.modelValue || {}) });
const accept = ref(props.acceptTerms);
let syncing = false;

watch(
  () => props.modelValue,
  (val) => {
    if (!val) return;
    const keys = Object.keys(createDefault());
    let changed = false;
    for (const key of keys) {
      const incoming = val[key] ?? createDefault()[key];
      if (form[key] !== incoming) {
        changed = true;
        break;
      }
    }
    if (!changed) return;
    syncing = true;
    for (const key of keys) {
      const incoming = val[key] ?? createDefault()[key];
      if (form[key] !== incoming) form[key] = incoming;
    }
    nextTick(() => {
      syncing = false;
    });
  },
  { deep: true },
);

watch(
  () => props.acceptTerms,
  (val) => {
    if (accept.value !== val) accept.value = val;
  },
);

watch(
  form,
  (val) => {
    if (syncing) return;
    emit("update:modelValue", { ...val });
  },
  { deep: true, flush: "post" },
);

watch(accept, (val) => emit("update:acceptTerms", val));

function handleSubmit() {
  emit("submit", {
    guest_name: form.name,
    guest_email: form.email,
    guest_phone: form.phone,
    guest_identity_type: form.identity_type,
    guest_identity_number: form.identity_number,
    guest_nationality: form.nationality,
    guest_company: form.company,
    special_request: form.special_request,
    accept_terms: accept.value,
  });
}
</script>
