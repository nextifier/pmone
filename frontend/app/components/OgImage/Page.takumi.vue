<script setup>
const props = defineProps({
  headline: {
    type: String,
    default: "",
  },
  pageTitle: {
    type: String,
    default: "",
  },
  pageDescription: {
    type: String,
    default: "",
  },
  brandName: {
    type: String,
    default: "",
  },
  website: {
    type: String,
    default: "",
  },
});

const isDarkMode = ref(useAppConfig().settings.ogImage.isDarkMode);
</script>

<template>
  <div
    class="relative flex h-full w-full flex-col px-[100px] py-[100px] antialiased"
    :class="isDarkMode ? 'bg-black text-white' : 'bg-white text-black'"
  >
    <div
      class="absolute inset-y-0 left-[100px] border-l-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-y-0 right-[100px] border-r-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-x-0 top-[100px] border-t-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div
      class="absolute inset-x-0 bottom-[100px] border-b-2 border-solid"
      :class="isDarkMode ? 'border-white/25' : 'border-black/15'"
    ></div>

    <div class="flex h-full flex-col text-left">
      <h1
        v-if="pageTitle"
        class="mt-2 shrink-0 text-[72px] leading-[1.1] font-normal tracking-[-0.06em]"
        style="line-clamp: 2; text-overflow: ellipsis; overflow: hidden"
      >
        {{ pageTitle }}
      </h1>
      <p
        v-if="pageDescription"
        class="mt-3 shrink-0 text-[32px] leading-[1.6] font-normal tracking-tighter text-pretty"
        style="line-clamp: 2; text-overflow: ellipsis; overflow: hidden"
      >
        {{ pageDescription }}
      </p>

      <div class="mt-auto flex flex-row items-center" style="transform: translateY(50%)">
        <div
          class="outline-inside flex size-20 items-center justify-center overflow-hidden rounded-2xl"
        >
          <img
            :src="`/brands/${useAppConfig().app.brandId}/icons/icon-192x192.png`"
            class="size-20 object-contain"
            alt=""
            width="80"
            height="80"
          />
        </div>

        <div class="ml-4 flex flex-col items-start gap-y-2">
          <span v-if="useAppConfig().app.name" class="text-3xl font-normal tracking-tighter">{{
            useAppConfig().app.name
          }}</span>
          <span v-if="useAppConfig().app.url" class="text-2xl font-normal tracking-tight">{{
            useAppConfig().app.url
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
