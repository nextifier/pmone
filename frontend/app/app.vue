<template>
  <Html>
    <Body class="bg-background text-foreground font-sans text-sm antialiased sm:text-base">
      <NuxtPwaManifest />
      <NuxtLoadingIndicator :color="false" class="bg-accent" />
      <NuxtLayout>
        <NuxtPage
          :keepalive="{
            include: ['users', 'logs'],
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
  useNuxtApp().$updateMetaThemeColor();
});
</script>
