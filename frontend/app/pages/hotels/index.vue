<template>
  <div class="min-h-screen-offset pt-6 pb-16">
    <Hotels tag="h1" />
  </div>
</template>

<script setup>
defineOptions({ name: "hotels" });

definePageMeta({
  layout: "default",
});

usePageMeta(null, {
  title: "Hotels",
  description:
    "Browse our partner hotels for the event and book your stay with secure online payment.",
});

const breadcrumbJsonLd = computed(() =>
  JSON.stringify({
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [
      { "@type": "ListItem", position: 1, name: "Home", item: "/" },
      { "@type": "ListItem", position: 2, name: "Hotels", item: "/hotels" },
    ],
  }),
);

useHead({
  script: [
    {
      type: "application/ld+json",
      innerHTML: breadcrumbJsonLd,
      tagPosition: "head",
    },
  ],
});
</script>
