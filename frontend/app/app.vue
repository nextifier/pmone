<template>
  <Html>
    <Body class="bg-background text-foreground font-sans text-sm antialiased sm:text-base">
      <NuxtPwaManifest />
      <NuxtLoadingIndicator />
      <NuxtLayout>
        <!-- Jika clientOnly di halaman {model}/index.vue bernilai false, artinya fungsi search, filter, sort, dan pagination dilakukan di backend, maka jangan tambahkan halaman tersebut ke keepalive, karena terdapat bug, jika user kembali ke halaman tersebut untuk ke-2 kali atau seterusnya, maka fungsi search, filter, sort, dan pagination menjadi tidak bekerja. -->
        <NuxtPage
          :keepalive="{
            include: ['dashboard', 'inbox', 'projects', 'links', 'users', 'api-consumers'],
          }"
        />
      </NuxtLayout>
      <ScrollToTop />
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
