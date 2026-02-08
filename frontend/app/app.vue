<template>
  <Html>
    <Body class="bg-background text-foreground font-sans text-sm antialiased sm:text-base">
      <NuxtPwaManifest />
      <NuxtLoadingIndicator />
      <NuxtLayout>
        <NuxtPage
          :keepalive="{
            include: ['dashboard', 'inbox', 'projects', 'links', 'posts', 'users', 'api-consumers'],
          }"
        />
      </NuxtLayout>
      <ScrollToTop v-if="!isTasksPage" />
      <Toaster class="pointer-events-auto" />
    </Body>
  </Html>
</template>

<script setup>
import "vue-sonner/style.css";

const route = useRoute();
const isTasksPage = computed(() => route.path === "/tasks");

onMounted(() => {
  const { updateMetaThemeColor } = useThemeSync();
  updateMetaThemeColor();
});
</script>
