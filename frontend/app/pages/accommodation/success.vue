<template>
  <div class="mx-auto max-w-2xl px-4 py-12 text-center space-y-4">
    <div class="flex justify-center">
      <div class="bg-success/10 text-success-foreground rounded-full p-4">
        <Icon name="lucide:check" class="size-8" />
      </div>
    </div>
    <h1 class="text-2xl font-semibold tracking-tighter">Pembayaran Berhasil</h1>
    <p class="text-muted-foreground tracking-tight">
      Terima kasih, kami sudah menerima pembayaran Anda<span v-if="ref"> untuk reservasi <strong class="text-foreground">{{ ref }}</strong></span>.
    </p>
    <p class="text-sm tracking-tight text-muted-foreground">
      Email konfirmasi sudah dikirim ke alamat email Anda. Tim kami akan koordinasi dengan hotel partner dan mengirimkan voucher check-in dalam 1-2 hari kerja.
    </p>
    <div class="flex justify-center gap-3 pt-4">
      <NuxtLink to="/accommodation" class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm tracking-tight">Browse More Hotels</NuxtLink>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: "public",
});

const route = useRoute();
const ref = computed(() => route.query.ref);

usePageMeta(null, {
  title: "Booking Successful · Accommodation",
});
</script>
