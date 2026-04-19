<template>
  <section class="space-y-4">
    <div v-if="hotel.featured?.lg" class="overflow-hidden rounded-lg">
      <img :src="hotel.featured.lg" :alt="hotel.name" class="aspect-video w-full object-cover" />
    </div>

    <div class="space-y-2">
      <h1 class="text-2xl sm:text-3xl font-semibold tracking-tighter">{{ hotel.name }}</h1>

      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <div v-if="hotel.star_rating" class="flex items-center gap-0.5" :aria-label="`${hotel.star_rating} star rating`">
          <Icon
            v-for="n in 5"
            :key="n"
            :name="n <= hotel.star_rating ? 'material-symbols:star-rounded' : 'material-symbols:star-outline-rounded'"
            :class="[
              'size-4',
              n <= hotel.star_rating ? 'text-warning' : 'text-muted-foreground/40',
            ]"
          />
          <span class="text-muted-foreground text-xs tracking-tight ml-1">
            {{ hotel.star_rating }}-star
          </span>
        </div>

        <p class="text-muted-foreground text-sm tracking-tight">
          {{ [hotel.address, hotel.city].filter(Boolean).join(", ") }}
        </p>
      </div>

      <p v-if="hotel.description" class="text-sm tracking-tight whitespace-pre-line">
        {{ hotel.description }}
      </p>

      <div v-if="hotel.google_maps_link" class="flex flex-wrap gap-2 pt-1">
        <a
          :href="hotel.google_maps_link"
          target="_blank"
          rel="noopener"
          class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:location-04" class="size-4 shrink-0" />
          Get Directions
        </a>
      </div>
    </div>

    <div v-if="hotel.facilities?.length" class="space-y-2">
      <h2 class="text-sm font-semibold tracking-tight">Hotel Facilities</h2>
      <div class="flex flex-wrap gap-1.5">
        <span
          v-for="facility in hotel.facilities"
          :key="facility"
          class="bg-muted rounded-full px-3 py-1 text-xs sm:text-sm tracking-tight"
        >
          {{ facility }}
        </span>
      </div>
    </div>

    <div v-if="hotel.google_maps_embed_src" class="overflow-hidden rounded-lg border">
      <iframe
        :src="hotel.google_maps_embed_src"
        class="aspect-video w-full"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        allowfullscreen
      />
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
