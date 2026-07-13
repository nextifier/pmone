<!-- Brand-agnostic wrapper: the actual wordmark lives in brands/<id>/Logo.vue,
     selected at build time via the BRAND env var (see brands/index.ts). -->
<template>
  <BrandLogo />
</template>

<script setup>
import BrandLogo from "#brand/Logo.vue";
</script>
