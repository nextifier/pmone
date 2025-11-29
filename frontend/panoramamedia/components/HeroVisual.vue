<template>
  <div ref="containerRef" class="group" data-layout="final">
    <!-- Mobile Layout: 4 rows x 3 cards -->
    <div
      class="relative isolate group-data-[layout=final]:flex group-data-[layout=final]:flex-col group-data-[layout=final]:-space-y-[1%] lg:hidden group-data-[layout=final]:lg:hidden group-data-[layout=stacked]:lg:hidden"
    >
      <div
        v-for="(row, rowIndex) in mobileRows"
        :key="`mobile-row-${rowIndex}`"
        class="relative flex origin-top scale-105 flex-wrap justify-center-safe -space-x-[2%] first:scale-110 nth-2:scale-105 nth-3:scale-100 nth-4:scale-95 lg:grid-cols-6"
        :style="{
          zIndex: mobileRows.length - rowIndex,
        }"
      >
        <div
          v-for="(item, cardIndex) in row"
          :key="`mobile-${item.slug}`"
          class="bg-background border-border flex aspect-4/5 basis-1/3 items-center justify-center overflow-hidden rounded-2xl border text-center first:translate-y-2 first:-rotate-4 last:translate-y-2 last:rotate-4 nth-2:rotate-0"
        >
          <!-- <NuxtImg
            :src="item.img"
            :alt="item.name"
            class="size-full object-cover"
            width="1080"
            height="1350"
            sizes="400px"
            format="webp"
          /> -->
          <span class="px-2 text-center text-xs tracking-tight sm:text-sm">{{
            item.name
          }}</span>
        </div>
      </div>
    </div>

    <!-- Desktop Layout: 2 rows x 6 cards -->
    <div
      class="relative isolate group-data-[layout=final]:hidden group-data-[layout=final]:flex-col group-data-[layout=final]:-space-y-[1%] group-data-[layout=final]:lg:flex"
    >
      <div
        v-for="(row, rowIndex) in desktopRows"
        :key="`desktop-row-${rowIndex}`"
        class="relative group-data-[layout=final]:flex group-data-[layout=final]:origin-top group-data-[layout=final]:flex-wrap group-data-[layout=final]:justify-center-safe group-data-[layout=final]:-space-x-[1%] group-data-[layout=final]:first:scale-105 group-data-[layout=final]:nth-2:scale-100 group-data-[layout=final]:lg:grid-cols-6"
        :style="{
          zIndex: desktopRows.length - rowIndex,
        }"
      >
        <div
          v-for="(item, cardIndex) in row"
          :key="`desktop-${item.slug}`"
          class="bg-pattern-diagonal bg-background/80 border-border flex aspect-4/5 items-center justify-center overflow-hidden rounded-2xl border text-center backdrop-blur-lg group-data-[layout=final]:basis-1/6 group-data-[layout=stacked]:absolute group-data-[layout=stacked]:top-0 group-data-[layout=stacked]:left-1/2 group-data-[layout=stacked]:-translate-x-1/2 group-data-[layout=final]:first:translate-y-8 group-data-[layout=final]:first:-rotate-6 group-data-[layout=final]:last:translate-y-8 group-data-[layout=final]:last:rotate-6 group-data-[layout=final]:nth-2:translate-y-3 group-data-[layout=final]:nth-2:-rotate-4 group-data-[layout=final]:nth-3:-rotate-2 group-data-[layout=final]:nth-last-2:translate-y-3 group-data-[layout=final]:nth-last-2:rotate-4 group-data-[layout=final]:nth-last-3:rotate-2"
        >
          <!-- <NuxtImg
            :src="item.img"
            :alt="item.name"
            class="size-full object-cover"
            width="1080"
            height="1350"
            sizes="400px"
            format="webp"
          /> -->
          <span class="px-2 text-center text-xs tracking-tight sm:text-sm">{{
            item.name
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { gsap } from "gsap";
// import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Flip } from "gsap/Flip";

const items = useProductStore().list;

const mobileRows = computed(() => {
  const rows = [];
  for (let i = 0; i < items.length; i += 3) {
    rows.push(items.slice(i, i + 3));
  }
  return rows;
});

const desktopRows = computed(() => {
  const rows = [];
  for (let i = 0; i < items.length; i += 6) {
    rows.push(items.slice(i, i + 6));
  }
  return rows;
});

const isInitialized = ref(false);
const containerRef = ref(null);

let layouts = ["stacked", "final"];
let ctx;

const initializeAnimation = async () => {
  if (typeof window === "undefined") return;

  await nextTick();

  gsap.registerPlugin(Flip);

  ctx = gsap.context(() => {
    isInitialized.value = true;
  }, containerRef.value);
};

onMounted(() => {
  initializeAnimation();
});

onUnmounted(() => {
  if (ctx) {
    ctx.revert();
  }
});
</script>
