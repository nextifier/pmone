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
            <Input id="guest_name" v-model="form.name" required :disabled="disabled" />
            <InputErrorMessage :errors="errors.guest_name || clientErrors.name" />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="guest_email">Email<span class="text-destructive">*</span></Label>
              <Input id="guest_email" v-model="form.email" type="email" required :disabled="disabled" />
              <InputErrorMessage :errors="errors.guest_email || clientErrors.email" />
            </div>
            <div class="space-y-2">
              <Label for="guest_phone">Phone<span class="text-destructive">*</span></Label>
              <InputPhone id="guest_phone" v-model="form.phone" :disabled="disabled" />
              <InputErrorMessage :errors="errors.guest_phone || clientErrors.phone" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="identity_type">ID Type<span class="text-destructive">*</span></Label>
              <Select v-model="form.identity_type" :disabled="disabled">
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
              <Input
                id="identity_number"
                v-model="form.identity_number"
                :placeholder="form.identity_type === 'nik' ? '16 digits NIK' : 'Passport number'"
                required
                :disabled="disabled"
              />
              <InputErrorMessage :errors="errors.guest_identity_number || clientErrors.identity_number" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>Nationality</Label>
              <LocationCombobox
                v-model="form.nationality"
                :options="countries"
                :pinned="['Indonesia']"
                placeholder="Select country"
                :disabled="disabled"
              />
              <InputErrorMessage :errors="errors.guest_nationality" />
            </div>
            <div class="space-y-2">
              <Label for="company">Company</Label>
              <Input id="company" v-model="form.company" :disabled="disabled" />
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
              :disabled="disabled"
            />
            <InputErrorMessage :errors="errors.special_request" />
          </div>

          <div class="space-y-2">
            <TermsCheckbox v-model="accept" :disabled="disabled" />
            <InputErrorMessage :errors="errors.accept_terms" />
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button type="button" variant="outline" :disabled="saving" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="!accept || saving || disabled">
        <Spinner v-if="saving" />
        {{ saving ? 'Processing...' : 'Confirm & Pay' }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import TermsCheckbox from './TermsCheckbox.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { InputErrorMessage } from '@/components/ui/input-error-message'
import { Label } from '@/components/ui/label'
import { LocationCombobox } from '@/components/ui/location-combobox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from '@/components/ui/select'
import { Spinner } from '@/components/ui/spinner'
import { Textarea } from '@/components/ui/textarea'
import { InputPhone } from '@/components/ui/input-phone'
import countries from '@/data/countries.json'
import { reactive, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  },
  acceptTerms: {
    type: Boolean,
    default: false
  },
  saving: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue', 'update:acceptTerms', 'submit', 'cancel'])

const createDefault = () => ({
  name: '',
  email: '',
  phone: '',
  identity_type: 'nik',
  identity_number: '',
  nationality: 'Indonesia',
  company: '',
  special_request: ''
})

const form = reactive({ ...createDefault(), ...(props.modelValue || {}) })
const accept = ref(props.acceptTerms)
const clientErrors = ref({})

watch(
  () => props.modelValue,
  (val) => {
    if (!val) return
    const keys = Object.keys(createDefault())
    for (const key of keys) {
      const incoming = val[key] ?? createDefault()[key]
      if (form[key] !== incoming) form[key] = incoming
    }
  },
  { deep: true }
)

watch(
  () => props.acceptTerms,
  (val) => {
    if (accept.value !== val) accept.value = val
  }
)

watch(
  form,
  (val) => {
    emit('update:modelValue', { ...val })
  },
  { deep: true }
)

watch(accept, (val) => emit('update:acceptTerms', val))

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const NIK_RE = /^\d{16}$/
const PASSPORT_RE = /^[A-Z0-9]{6,15}$/i
const PHONE_RE = /^\+?\d[\d\s\-]{5,20}$/

function validate() {
  const errs = {}

  if (!form.name?.trim() || form.name.trim().length < 2) {
    errs.name = ['Name must be at least 2 characters.']
  }
  if (!form.email || !EMAIL_RE.test(form.email.trim())) {
    errs.email = ['Please enter a valid email address.']
  }
  if (!form.phone || !PHONE_RE.test(String(form.phone).trim())) {
    errs.phone = ['Please enter a valid phone number.']
  }
  if (!form.identity_number?.trim()) {
    errs.identity_number = ['ID number is required.']
  } else if (form.identity_type === 'nik' && !NIK_RE.test(form.identity_number.trim())) {
    errs.identity_number = ['NIK must be exactly 16 digits.']
  } else if (form.identity_type === 'passport' && !PASSPORT_RE.test(form.identity_number.trim())) {
    errs.identity_number = ['Passport number must be 6-15 alphanumeric characters.']
  }

  clientErrors.value = errs
  return Object.keys(errs).length === 0
}

function handleSubmit() {
  if (!validate()) return
  emit('submit', {
    guest_name: form.name.trim(),
    guest_email: form.email.trim(),
    guest_phone: String(form.phone).trim(),
    guest_identity_type: form.identity_type,
    guest_identity_number: form.identity_number.trim(),
    guest_nationality: form.nationality,
    guest_company: form.company,
    special_request: form.special_request,
    accept_terms: accept.value
  })
}
</script>
