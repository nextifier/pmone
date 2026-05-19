<script setup>
// Legacy URL redirect: /accommodation/* → /hotels/*
// Used for backward-compat dengan Xendit invoice yang punya success_redirect lama.

const route = useRoute();
const slug = Array.isArray(route.params.slug) ? route.params.slug.join("/") : route.params.slug ?? "";
const query = new URLSearchParams(route.query).toString();
const target = "/hotels" + (slug ? "/" + slug : "") + (query ? "?" + query : "");

await navigateTo(target, { redirectCode: 301, replace: true });
</script>

<template>
  <div class="flex min-h-[60vh] items-center justify-center text-muted-foreground tracking-tight">
    Redirecting…
  </div>
</template>
