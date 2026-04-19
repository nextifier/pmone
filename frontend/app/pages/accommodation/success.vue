<template>
  <div class="mx-auto max-w-2xl px-4 pt-4 pb-16 space-y-6 text-center">
    <div class="flex justify-center pt-8">
      <div class="bg-success/10 text-success-foreground rounded-full p-4">
        <Icon name="lucide:check" class="size-8" />
      </div>
    </div>
    <div class="space-y-2">
      <h1 class="page-title">Payment Successful</h1>
      <p class="text-muted-foreground tracking-tight">
        Thank you, your payment has been received.
      </p>
    </div>

    <div
      v-if="bookingRef"
      class="mx-auto max-w-sm rounded-md border bg-muted/30 p-4 space-y-1"
    >
      <p class="text-muted-foreground text-xs tracking-tight uppercase">Reservation number</p>
      <p class="font-mono text-lg font-semibold tracking-tight">{{ bookingRef }}</p>
      <button
        type="button"
        class="text-primary text-xs hover:underline"
        @click="copyRef"
      >
        {{ copied ? 'Copied!' : 'Copy reference' }}
      </button>
    </div>

    <p class="text-muted-foreground text-sm tracking-tight">
      A confirmation email has been sent to your inbox. Our team will coordinate with the partner hotel and send your check-in voucher within 1-2 business days.
    </p>

    <div v-if="magicToken" class="mx-auto max-w-sm text-xs text-muted-foreground tracking-tight">
      <p>Bookmark this link to check your reservation status anytime:</p>
      <NuxtLink :to="`/hotels/reservation/${magicToken}`" class="text-primary hover:underline break-all">
        View reservation details
      </NuxtLink>
    </div>

    <div class="flex justify-center gap-3 pt-2">
      <NuxtLink
        to="/accommodation"
        class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm tracking-tight active:scale-98"
      >
        Browse More Hotels
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'

definePageMeta({
  layout: "public",
});

const route = useRoute();
const bookingRef = computed(() => route.query.ref);
const magicToken = computed(() => route.query.token);

usePageMeta(null, {
  title: "Booking Successful · Accommodation",
});

const copied = ref(false)
async function copyRef() {
  if (!bookingRef.value) return
  try {
    await navigator.clipboard.writeText(String(bookingRef.value))
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    toast.error('Copy failed', { description: 'Please copy the number manually.' })
  }
}
</script>
