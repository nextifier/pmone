<template>
  <div />
</template>

<script setup>
// "SEO Meta" and "OG Images" were merged into a single "SEO" tab. Keep this
// route as a permanent redirect so old bookmarks and deep links still work.
definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const route = useRoute();

await navigateTo(`/projects/${route.params.username}/settings/seo`, {
  redirectCode: 301,
  replace: true,
});
</script>
