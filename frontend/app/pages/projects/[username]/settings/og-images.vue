<template>
  <div />
</template>

<script setup>
// "OG Images" was merged into the "SEO" tab (share preview per page). Keep this
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
