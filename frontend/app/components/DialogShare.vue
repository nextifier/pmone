<template>
  <DialogResponsive>
    <template #trigger="{ open }">
      <button
        type="button"
        @click="open({ title: pageTitle })"
        class="text-primary/80 hover:text-primary flex items-center justify-center gap-x-1 text-sm tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:share-03" class="size-4.5 shrink-0" />
        <span>Share</span>
      </button>
    </template>

    <template #default="{ data }">
      <div class="px-4 pb-6 md:p-8">
        <div class="text-primary text-center text-lg font-semibold tracking-tight sm:text-xl">
          Share this page
        </div>
        <SharePage :title="data.title || ''" :url="fullUrl" class="mt-4" />
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
const props = defineProps({
  pageTitle: {
    type: String,
    required: true,
  },
});

const config = useRuntimeConfig();
const route = useRoute();

const fullUrl = computed(() => `${useAppConfig().app.url}${route.fullPath}`);
</script>
