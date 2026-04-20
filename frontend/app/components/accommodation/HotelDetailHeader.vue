<template>
  <section class="space-y-4">
    <div v-if="hotel.featured?.lg" class="overflow-hidden rounded-lg">
      <img
        :src="hotel.featured.lg"
        :alt="hotel.name"
        class="aspect-video w-full object-cover"
        loading="eager"
        decoding="async"
      />
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

      <div class="flex flex-wrap gap-2 pt-1">
        <a
          v-if="hotel.google_maps_link"
          :href="hotel.google_maps_link"
          target="_blank"
          rel="noopener noreferrer"
          class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:location-04" class="size-4 shrink-0" />
          Get Directions
        </a>
        <a
          v-if="hotel.contact_phone"
          :href="`tel:${hotel.contact_phone}`"
          class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:phone" class="size-4 shrink-0" />
          {{ hotel.contact_phone }}
        </a>
        <a
          v-if="hotel.contact_email"
          :href="`mailto:${hotel.contact_email}`"
          class="border-border hover:bg-muted inline-flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:mail" class="size-4 shrink-0" />
          {{ hotel.contact_email }}
        </a>
      </div>

      <div v-if="checkInCheckOutText" class="text-muted-foreground text-xs tracking-tight pt-1">
        {{ checkInCheckOutText }}
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

    <div v-if="hotel.google_maps_embed_src" class="overflow-hidden rounded-lg border relative">
      <iframe
        v-if="mapLoaded"
        :src="hotel.google_maps_embed_src"
        class="aspect-video w-full"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        allowfullscreen
        :title="`${hotel.name} map`"
      />
      <button
        v-else
        type="button"
        class="bg-muted hover:bg-muted/80 aspect-video w-full flex flex-col items-center justify-center gap-2 text-muted-foreground"
        @click="mapLoaded = true"
      >
        <Icon name="hugeicons:location-04" class="size-10" />
        <span class="text-sm tracking-tight">Load interactive map</span>
        <span class="text-xs tracking-tight opacity-70">Loads content from Google Maps</span>
      </button>
    </div>

    <div v-if="hotel.gallery?.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
      <div v-for="img in hotel.gallery" :key="img.id" class="bg-muted aspect-square overflow-hidden rounded">
        <img
          :src="img.sm"
          :alt="hotel.name"
          class="size-full object-cover"
          loading="lazy"
          decoding="async"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, useHead } from '#imports'

const props = defineProps({
  hotel: { type: Object, required: true },
})

const mapLoaded = ref(false)

const checkInCheckOutText = computed(() => 'Check-in from 14:00 · check-out by 12:00')

const jsonLd = computed(() => {
  const h = props.hotel
  if (!h) return null
  const data = {
    '@context': 'https://schema.org',
    '@type': 'Hotel',
    name: h.name,
    description: h.description || undefined,
    address: h.address || h.city
      ? {
          '@type': 'PostalAddress',
          streetAddress: h.address || undefined,
          addressLocality: h.city || undefined,
          addressCountry: h.country || undefined,
        }
      : undefined,
    image: h.featured?.lg || h.featured?.md || undefined,
    telephone: h.contact_phone || undefined,
    email: h.contact_email || undefined,
    starRating: h.star_rating
      ? { '@type': 'Rating', ratingValue: h.star_rating, bestRating: 5 }
      : undefined,
    url: h.website_url || undefined,
  }
  return JSON.stringify(data)
})

useHead({
  script: computed(() =>
    jsonLd.value
      ? [{ type: 'application/ld+json', innerHTML: jsonLd.value, tagPosition: 'head' }]
      : []
  ),
})
</script>
