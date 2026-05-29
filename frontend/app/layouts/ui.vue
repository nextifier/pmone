<template>
  <SidebarProvider :style="{ '--sidebar-width': '18rem' }">
    <UiSidebar :current-name="currentName" />
    <SidebarInset>
      <UiHeader />
      <div class="min-h-screen-offset">
        <div class="container-wider">
          <slot />
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const route = useRoute();
const currentName = computed(() => route.params.name || "");
</script>
