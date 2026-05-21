<script setup>
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "../ui/collapsible";
import { Input } from "../ui/input";
import { InputErrorMessage } from "../ui/input-error-message";
import { InputPhone } from "../ui/input-phone";
import { Label } from "../ui/label";
import { LocationCombobox } from "../ui/location-combobox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select";
import { Textarea } from "../ui/textarea";
import countries from "../../data/countries.json";
import { computed, ref, watch } from "vue";

const props = defineProps({
  guest: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  prefilledFromProfile: { type: Boolean, default: false },
});

const emit = defineEmits(["update"]);

const showOptional = ref(!!(props.guest.company || props.guest.special_request));
const clientErrors = ref({});

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const NIK_RE = /^\d{16}$/;
const PASSPORT_RE = /^[A-Z0-9]{6,15}$/i;
const PHONE_RE = /^\+?\d[\d\s-]{5,20}$/;

function update(field, value) {
  emit("update", { [field]: value });
}

function validateField(field) {
  const value = props.guest[field];
  const next = { ...clientErrors.value };
  delete next[field];

  if (field === "email" && value && !EMAIL_RE.test(value.trim())) {
    next.email = ["Please enter a valid email address."];
  }
  if (field === "phone" && value && !PHONE_RE.test(String(value).trim())) {
    next.phone = ["Please enter a valid phone number."];
  }
  if (field === "identity_number" && value) {
    const v = value.trim();
    if (props.guest.identity_type === "nik" && !NIK_RE.test(v)) {
      next.identity_number = ["NIK must be exactly 16 digits."];
    } else if (props.guest.identity_type === "passport" && !PASSPORT_RE.test(v)) {
      next.identity_number = ["Passport must be 6-15 alphanumeric characters."];
    }
  }
  clientErrors.value = next;
}

const mergedErrors = computed(() => ({
  name: props.errors.guest_name || clientErrors.value.name,
  email: props.errors.guest_email || clientErrors.value.email,
  phone: props.errors.guest_phone || clientErrors.value.phone,
  identity_type: props.errors.guest_identity_type,
  identity_number: props.errors.guest_identity_number || clientErrors.value.identity_number,
  nationality: props.errors.guest_nationality,
}));

watch(() => props.guest.identity_type, () => validateField("identity_number"));
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-base font-medium tracking-tight">Primary Guest Information</h2>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Voucher and invoice will be sent to the email and phone below.
      </p>
    </div>

    <div
      v-if="prefilledFromProfile"
      class="border-success/30 bg-success/5 flex items-start gap-2 rounded-lg border px-3 py-2 text-sm tracking-tight"
    >
      <Icon name="hugeicons:tick-double-02" class="text-success mt-0.5 size-4 shrink-0" />
      <span class="text-foreground">Pre-filled from your profile.</span>
    </div>

    <section class="bg-background space-y-5 rounded-2xl border p-4 sm:p-5">
      <div class="space-y-2">
        <Label for="guest_name">Full Name</Label>
        <Input
          id="guest_name"
          :model-value="guest.name"
          required
          @update:model-value="(v) => update('name', v)"
        />
        <InputErrorMessage :errors="mergedErrors.name" />
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="guest_email">Email</Label>
          <Input
            id="guest_email"
            :model-value="guest.email"
            type="email"
            required
            @update:model-value="(v) => update('email', v)"
            @blur="validateField('email')"
          />
          <InputErrorMessage :errors="mergedErrors.email" />
        </div>
        <div class="space-y-2">
          <Label for="guest_phone">Phone</Label>
          <InputPhone
            id="guest_phone"
            :model-value="guest.phone"
            required
            @update:model-value="(v) => update('phone', v)"
            @blur="validateField('phone')"
          />
          <InputErrorMessage :errors="mergedErrors.phone" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="identity_type">ID Type</Label>
          <Select
            :model-value="guest.identity_type"
            @update:model-value="(v) => update('identity_type', v)"
          >
            <SelectTrigger id="identity_type" class="w-full">
              <SelectValue placeholder="Select ID type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="nik">NIK (KTP)</SelectItem>
              <SelectItem value="passport">Passport</SelectItem>
            </SelectContent>
          </Select>
          <InputErrorMessage :errors="mergedErrors.identity_type" />
        </div>
        <div class="space-y-2">
          <Label for="identity_number">ID Number</Label>
          <Input
            id="identity_number"
            :model-value="guest.identity_number"
            :placeholder="guest.identity_type === 'nik' ? '16 digits NIK' : 'Passport number'"
            required
            @update:model-value="(v) => update('identity_number', v)"
            @blur="validateField('identity_number')"
          />
          <InputErrorMessage :errors="mergedErrors.identity_number" />
        </div>
      </div>

      <div class="space-y-2">
        <Label>Nationality</Label>
        <LocationCombobox
          :model-value="guest.nationality"
          :options="countries"
          :pinned="['Indonesia']"
          placeholder="Select country"
          @update:model-value="(v) => update('nationality', v)"
        />
        <InputErrorMessage :errors="mergedErrors.nationality" />
      </div>
    </section>

    <Collapsible v-model:open="showOptional">
      <CollapsibleTrigger
        class="text-foreground hover:text-foreground/80 inline-flex w-full items-center justify-between rounded-lg px-1 text-sm font-medium tracking-tight"
      >
        <span class="inline-flex items-center gap-2">
          <Icon name="hugeicons:settings-02" class="size-4" />
          Additional info (optional)
        </span>
        <Icon
          name="hugeicons:arrow-down-01"
          class="size-4 transition-transform"
          :class="{ 'rotate-180': showOptional }"
        />
      </CollapsibleTrigger>
      <CollapsibleContent class="mt-3 space-y-5">
        <div class="space-y-2">
          <Label for="company">Company</Label>
          <Input
            id="company"
            :model-value="guest.company"
            @update:model-value="(v) => update('company', v)"
          />
        </div>
        <div class="space-y-2">
          <Label for="special_request">Special Request</Label>
          <Textarea
            id="special_request"
            :model-value="guest.special_request"
            rows="3"
            placeholder="Late check-in, dietary, etc."
            @update:model-value="(v) => update('special_request', v)"
          />
        </div>
      </CollapsibleContent>
    </Collapsible>
  </div>
</template>
