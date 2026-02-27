<template>
  <Html>
    <Body class="bg-background text-foreground font-sans text-sm antialiased sm:text-base">
      <NuxtPwaManifest />
      <NuxtLoadingIndicator
        color="linear-gradient(90deg, oklch(0.595 0.196 254.96) 0%, oklch(0.659 0.187 252.32) 7%, oklch(0.775 0.121 247.99) 25%, oklch(0.953 0.083 144.45) 49%, oklch(0.875 0.175 92.22) 75%, oklch(0.7 0.202 44.18) 95%, oklch(0.676 0.217 38.8) 100%)"
      />
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
