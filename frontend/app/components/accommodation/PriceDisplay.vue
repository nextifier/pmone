<template>
  <div class="text-right">
    <p class="text-lg sm:text-xl font-semibold tracking-tighter">
      Rp {{ formatRupiah(baseRate) }}
      <span class="text-muted-foreground text-xs sm:text-sm font-normal">/ night</span>
    </p>
    <p v-if="allInRate" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
      Rp {{ formatRupiah(allInRate) }} all-in (incl. tax & service)
    </p>
  </div>
</template>

<script setup>
defineProps({
  baseRate: { type: Number, required: true },
  allInRate: { type: Number, default: 0 },
});

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>
