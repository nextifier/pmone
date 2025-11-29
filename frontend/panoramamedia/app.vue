<template>
  <Html>
    <Body
      class="bg-background text-body font-sans text-sm antialiased sm:text-base"
    >
      <NuxtLoadingIndicator :color="false" class="bg-accent" />
      <NuxtLayout>
        <NuxtPage
          :keepalive="{
            include: ['index', 'news'],
          }"
        />
      </NuxtLayout>
      <Toaster class="pointer-events-auto" />
    </Body>
  </Html>
</template>

<script setup>
import "vue-sonner/style.css";
const route = useRoute();

onMounted(() => {
  useNuxtApp().$updateMetaThemeColor();
});
</script>
