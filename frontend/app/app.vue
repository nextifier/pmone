<template>
  <Html>
    <Body class="bg-background text-foreground font-sans text-sm antialiased sm:text-base">
      <NuxtPwaManifest />
      <NuxtLoadingIndicator />
      <NuxtLayout>
        <NuxtPage
          :keepalive="{
            include: [],
          }"
        />
      </NuxtLayout>
      <Toaster class="pointer-events-auto" />
    </Body>
  </Html>
</template>

<script setup>
import "vue-sonner/style.css";

onMounted(() => {
  const { updateMetaThemeColor } = useThemeSync();
  updateMetaThemeColor();
});
</script>
