<template>
  <SidebarProvider>
    <SidebarInset>
      <Header />
      <main class="flex-auto">
        <slot />
      </main>

      <FAB
        v-if="
          ![
            'events',
            'events-slug',
            'news',
            'news-slug',
            'links',
            'winner',
          ].includes(route.name)
        "
      />

      <Footer v-if="!['links', 'winner'].includes(route.name)" />

      <DialogInquiry />
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
const route = useRoute();
</script>
