<script setup>
const props = defineProps({
  headline: {
    type: String,
    default: "headline",
  },
  title: {
    type: String,
    default: "title",
  },
  description: {
    type: String,
    default: "description",
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
    class="relative flex h-full w-full flex-col px-[100px] py-[100px]"
    :class="isDarkMode ? 'bg-black text-white' : 'bg-white text-black'"
  >
    <div
      class="absolute inset-y-0 left-[100px] border-l-2 border-dashed"
      :class="isDarkMode ? 'bg-white/15' : 'bg-black/10'"
    ></div>

    <div
      class="absolute inset-y-0 right-[100px] border-r-2 border-dashed"
      :class="isDarkMode ? 'bg-white/15' : 'bg-black/10'"
    ></div>

    <div
      class="absolute inset-x-0 top-[100px] border-t-2 border-dashed"
      :class="isDarkMode ? 'bg-white/15' : 'bg-black/10'"
    ></div>

    <div
      class="absolute inset-x-0 bottom-[100px] border-b-2 border-dashed"
      :class="isDarkMode ? 'bg-white/15' : 'bg-black/10'"
    ></div>

    <div class="flex h-full flex-col text-left">
      <h1
        v-if="title"
        class="mt-2 text-[80px] leading-none font-semibold tracking-tighter"
      >
        <span v-if="title.length > 40">{{ title.slice(0, 40) }}...</span>
        <span v-else>{{ title }}</span>
      </h1>
      <p
        v-if="description"
        class="mt-2 text-[28px] leading-[1.6] tracking-tight text-balance"
      >
        <span v-if="description.length > 160"
          >{{ description.slice(0, 160) }}...</span
        >
        <span v-else>{{ description }}</span>
      </p>

      <div class="mt-auto flex flex-row items-center gap-x-5">
        <div
          class="flex size-24 items-center justify-center overflow-hidden rounded-full border"
          :class="isDarkMode ? 'border-white/15' : 'border-black/10'"
        >
          <img
            src="/icons/icon-192x192.png"
            class="size-full object-contain"
            alt=""
            width="192"
            height="192"
          />
        </div>

        <div class="flex flex-col items-start gap-y-2">
          <span
            v-if="useAppConfig().app.name"
            class="text-3xl font-semibold tracking-tighter"
            >{{ useAppConfig().app.name }}</span
          >
          <span v-if="useAppConfig().app.url" class="text-2xl tracking-tight">{{
            useAppConfig().app.url
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
