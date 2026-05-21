<template>
  <div class="space-y-2">
    <label class="flex items-start gap-2 text-sm tracking-tight pt-2">
      <Checkbox
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', $event)"
      />
      <span>
        I agree to the
        <button type="button" class="text-primary underline" @click="termsOpen = true">
          terms &amp; conditions
        </button>
        and
        <button type="button" class="text-primary underline" @click="privacyOpen = true">
          privacy policy
        </button>.
      </span>
    </label>

    <DialogResponsive v-model:open="termsOpen" :overflow-content="true" dialog-max-width="36rem">
      <template #default>
        <div class="px-4 pb-8 md:px-6 md:py-5 space-y-3 text-sm tracking-tight">
          <h3 class="text-lg font-semibold">Terms &amp; Conditions</h3>
          <ul class="text-muted-foreground list-disc list-inside space-y-2">
            <li>All reservations are subject to availability and confirmation by the partner hotel.</li>
            <li>Payment is due in full at the time of booking via the payment gateway.</li>
            <li>Rates are quoted in Indonesian Rupiah (IDR) and include applicable taxes and service charges.</li>
            <li>Check-in and check-out times are set by each partner hotel.</li>
            <li>Cancellation policy: 100% refund if cancelled 14+ days before check-in, 50% if cancelled 7–13 days before, no refund within 7 days of check-in.</li>
            <li>Guest is responsible for presenting valid identification matching the reservation details at check-in.</li>
            <li>We reserve the right to refuse or cancel bookings in case of suspected fraud or payment issues.</li>
          </ul>
          <div class="flex justify-end pt-2">
            <Button size="sm" @click="termsOpen = false">Close</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <DialogResponsive v-model:open="privacyOpen" :overflow-content="true" dialog-max-width="36rem">
      <template #default>
        <div class="px-4 pb-8 md:px-6 md:py-5 space-y-3 text-sm tracking-tight">
          <h3 class="text-lg font-semibold">Privacy Policy</h3>
          <ul class="text-muted-foreground list-disc list-inside space-y-2">
            <li>We collect personal data (name, email, phone, ID) solely to process your reservation and communicate with the partner hotel.</li>
            <li>Payment is processed securely by Xendit; we do not store card details on our servers.</li>
            <li>Your information will be shared with the partner hotel to facilitate check-in and the services booked.</li>
            <li>We retain reservation records for legal and accounting purposes; you may request deletion of marketing-only data at any time.</li>
            <li>For privacy-related questions, contact the event organizer whose hotel you booked.</li>
          </ul>
          <div class="flex justify-end pt-2">
            <Button size="sm" @click="privacyOpen = false">Close</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from '../ui/button'
import { Checkbox } from '../ui/checkbox'
import DialogResponsive from '../ui/dialog-responsive/DialogResponsive.vue'
import { ref } from 'vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const termsOpen = ref(false)
const privacyOpen = ref(false)
</script>
