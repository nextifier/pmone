<template>
  <div />
</template>

<script setup>
/**
 * The Fields tab was folded into the Editor tab, which now shows the field list
 * beside a live preview. Kept as a redirect so existing links and bookmarks
 * still land somewhere sensible.
 */
definePageMeta({
  middleware: (to) => navigateTo(`/forms/${to.params.slug}`, { replace: true }),
});
</script>
