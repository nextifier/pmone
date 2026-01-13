<template>
  <SidebarProvider :defaultOpen="defaultOpen" id="layout-post-editor">
    <slot />
  </SidebarProvider>
</template>

<script setup>
const defaultOpen = useCookie("sidebar_state");
</script>
