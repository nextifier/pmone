<script setup>
// Legacy URL redirect: /accommodation → /hotels
await navigateTo("/hotels", { redirectCode: 301, replace: true });
</script>

<template>
  <div class="flex min-h-[60vh] items-center justify-center text-muted-foreground tracking-tight">
    Redirecting…
  </div>
</template>
