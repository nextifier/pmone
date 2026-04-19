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
        Thank you, your payment has been received<span v-if="bookingRef">
          for reservation <strong class="text-foreground">{{ bookingRef }}</strong></span>.
      </p>
    </div>
    <p class="text-muted-foreground text-sm tracking-tight">
      A confirmation email has been sent to your inbox. Our team will coordinate with the partner hotel and send your check-in voucher within 1-2 business days.
    </p>
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
definePageMeta({
  layout: "public",
});

const route = useRoute();
const bookingRef = computed(() => route.query.ref);

usePageMeta(null, {
  title: "Booking Successful · Accommodation",
});
</script>
