<!-- Brand-agnostic shell: the actual landing page lives in brands/<id>/Home.vue,
     selected at build time via the BRAND env var (see brands/index.ts). -->
<template>
  <BrandHome />
</template>

<script setup>
import BrandHome from "#brand/Home.vue";

definePageMeta({ scrollToTop: true });

const appConfig = useAppConfig();

usePageMeta(null, { title: appConfig.app.name, withoutTitleTemplate: true });
</script>
