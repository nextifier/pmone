<template>
  <section class="space-y-4">
    <div v-if="hotel.featured?.lg" class="overflow-hidden rounded-lg">
      <img :src="hotel.featured.lg" :alt="hotel.name" class="aspect-video w-full object-cover" />
    </div>

    <div class="space-y-2">
      <h1 class="text-2xl sm:text-3xl font-semibold tracking-tighter">{{ hotel.name }}</h1>
      <p class="text-muted-foreground text-sm tracking-tight">{{ [hotel.address, hotel.city].filter(Boolean).join(", ") }}</p>
      <p v-if="hotel.description" class="text-sm tracking-tight whitespace-pre-line">{{ hotel.description }}</p>
    </div>

    <div v-if="hotel.gallery?.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
      <div v-for="img in hotel.gallery" :key="img.id" class="bg-muted aspect-square overflow-hidden rounded">
        <img :src="img.sm" :alt="hotel.name" class="size-full object-cover" />
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  hotel: { type: Object, required: true },
});
</script>
