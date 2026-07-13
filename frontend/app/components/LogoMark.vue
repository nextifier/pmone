<!-- Brand-agnostic wrapper: the actual mark lives in brands/<id>/LogoMark.vue,
     selected at build time via the BRAND env var (see brands/index.ts). -->
<template>
  <BrandLogoMark />
</template>

<script setup>
import BrandLogoMark from "#brand/LogoMark.vue";
</script>
