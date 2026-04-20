<template>
  <section class="space-y-6">
    <div class="grid gap-5 lg:grid-cols-[12rem_1fr_12rem] lg:items-start">
      <div class="bg-muted aspect-4/5 w-full overflow-hidden rounded-xl lg:w-48">
        <img
          v-if="hotel.featured?.lg || hotel.featured?.url"
          :src="hotel.featured?.lg || hotel.featured?.url"
          :alt="hotel.name"
          class="size-full object-cover select-none"
          loading="eager"
          decoding="async"
        />
      </div>

      <div class="flex min-w-0 flex-col items-start gap-y-2 lg:pt-3">
        <p
          v-if="hotel.event?.title"
          class="text-muted-foreground inline-flex flex-wrap gap-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span>{{ hotel.event.title }}</span>
          <template v-if="eventDatesText">
            <span>·</span>
            <span>{{ eventDatesText }}</span>
          </template>
        </p>

        <h1 class="text-xl font-semibold tracking-tighter sm:text-2xl">{{ hotel.name }}</h1>

        <div v-if="hotel.star_rating" class="flex flex-wrap items-center gap-1.5">
          <span
            class="text-primary border-primary/30 bg-primary/5 inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium tracking-tight"
          >
            <Icon name="material-symbols:star-rounded" class="size-3.5" />
            {{ hotel.star_rating }}-star
          </span>
        </div>

        <div class="mt-2 flex flex-col gap-y-4">
          <div v-for="meta in metaItems" :key="meta.label" class="flex items-start gap-x-2">
            <div
              class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg"
            >
              <Icon :name="meta.icon" class="size-4" />
            </div>
            <div class="min-w-0">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ meta.label }}
              </p>
              <a
                v-if="meta.href"
                :href="meta.href"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium tracking-tight hover:underline"
              >
                {{ meta.value }}
              </a>
              <p v-else class="text-sm font-medium tracking-tight">{{ meta.value }}</p>
              <a
                v-if="meta.subtitleHref"
                :href="meta.subtitleHref"
                class="text-muted-foreground block text-xs tracking-tight hover:underline sm:text-sm"
              >
                {{ meta.subtitle }}
              </a>
              <p
                v-else-if="meta.subtitle"
                class="text-muted-foreground text-xs tracking-tight sm:text-sm"
              >
                {{ meta.subtitle }}
              </p>
            </div>
          </div>
        </div>

        <p v-if="hotel.description" class="mt-3 text-sm tracking-tight whitespace-pre-line">
          {{ hotel.description }}
        </p>
      </div>

      <div class="bg-muted aspect-4/5 w-full overflow-hidden rounded-xl lg:w-48">
        <iframe
          v-if="mapEmbedUrl"
          :src="mapEmbedUrl"
          class="size-full"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen
          :title="`${hotel.name} map`"
        />
      </div>
    </div>

    <div v-if="hotel.facilities?.length" class="space-y-2">
      <h2 class="text-sm font-semibold tracking-tight">Hotel Facilities</h2>
      <div class="flex flex-wrap gap-1.5">
        <span
          v-for="facility in hotel.facilities"
          :key="facility"
          class="bg-muted rounded-full px-3 py-1 text-xs tracking-tight sm:text-sm"
        >
          {{ facility }}
        </span>
      </div>
    </div>

    <div v-if="hotel.gallery?.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
      <div
        v-for="img in hotel.gallery"
        :key="img.id"
        class="bg-muted aspect-square overflow-hidden rounded"
      >
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
import { computed, useHead } from "#imports";

const props = defineProps({
  hotel: { type: Object, required: true },
});

const eventDatesText = computed(() => {
  const ev = props.hotel?.event;
  if (!ev) return null;
  const fmt = (d) =>
    d
      ? new Date(d).toLocaleDateString("en-GB", {
          day: "numeric",
          month: "short",
          year: "numeric",
        })
      : "";
  if (ev.start_date && ev.end_date) return `${fmt(ev.start_date)} - ${fmt(ev.end_date)}`;
  return fmt(ev.start_date || ev.end_date) || null;
});

const metaItems = computed(() => {
  const h = props.hotel;
  if (!h) return [];
  const parts = [h.address, h.city, h.country].filter(Boolean);
  const items = [
    {
      label: "Location",
      icon: "hugeicons:location-01",
      value: parts.join(", ") || "-",
      href: h.google_maps_link || undefined,
    },
  ];
  if (h.contact_email || h.contact_phone) {
    items.push({
      label: "Contact",
      icon: "hugeicons:mail-01",
      value: h.contact_email || h.contact_phone,
      href: h.contact_email ? `mailto:${h.contact_email}` : `tel:${h.contact_phone}`,
      subtitle: h.contact_email && h.contact_phone ? h.contact_phone : undefined,
      subtitleHref: h.contact_email && h.contact_phone ? `tel:${h.contact_phone}` : undefined,
    });
  }
  items.push({
    label: "Check-in / Check-out",
    icon: "hugeicons:clock-01",
    value: "14:00 / 12:00",
  });
  return items;
});

const mapEmbedUrl = computed(() => {
  const h = props.hotel;
  if (!h) return null;
  const queryParts = [h.name, h.address, h.city, h.country].filter(Boolean).join(", ");
  if (queryParts) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(queryParts)}&output=embed`;
  }
  const src = h.google_maps_embed_src?.trim();
  if (src && /^https:\/\/www\.google\.com\/maps\/embed\?pb=/.test(src)) {
    return src;
  }
  return null;
});

const jsonLd = computed(() => {
  const h = props.hotel;
  if (!h) return null;
  const data = {
    "@context": "https://schema.org",
    "@type": "Hotel",
    name: h.name,
    description: h.description || undefined,
    address:
      h.address || h.city
        ? {
            "@type": "PostalAddress",
            streetAddress: h.address || undefined,
            addressLocality: h.city || undefined,
            addressCountry: h.country || undefined,
          }
        : undefined,
    image: h.featured?.lg || h.featured?.md || undefined,
    telephone: h.contact_phone || undefined,
    email: h.contact_email || undefined,
    starRating: h.star_rating
      ? { "@type": "Rating", ratingValue: h.star_rating, bestRating: 5 }
      : undefined,
    url: h.website_url || undefined,
  };
  return JSON.stringify(data);
});

useHead({
  script: computed(() =>
    jsonLd.value
      ? [{ type: "application/ld+json", innerHTML: jsonLd.value, tagPosition: "head" }]
      : []
  ),
});
</script>
