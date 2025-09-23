<template>
  <DialogResponsive>
    <template #trigger="{ open }">
      <button
        type="button"
        @click="open({ title: pageTitle })"
        class="text-primary lg:hover:bg-muted flex items-center justify-center gap-x-1 rounded-full border p-3 transition active:scale-98 lg:border-0"
        :class="
          isSemiTransparent
            ? 'bg-background/70 border border-white/10 shadow-lg backdrop-blur-sm'
            : 'bg-background border-border'
        "
        v-ripple
      >
        <Icon name="lucide:share" class="size-4 shrink-0" />
        <span class="hidden text-sm tracking-tight lg:block">Share</span>
      </button>
    </template>

    <template #default="{ data }">
      <div class="px-4 pb-6 md:p-8">
        <div
          class="text-primary text-center text-lg font-semibold tracking-tight sm:text-xl"
        >
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
  isSemiTransparent: {
    type: Boolean,
    default: false,
  },
});

const config = useRuntimeConfig();
const route = useRoute();

const fullUrl = computed(() => `${useAppConfig().app.url}${route.fullPath}`);
</script>
