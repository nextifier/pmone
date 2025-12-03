<template>
  <div
    class="xs:right-[calc(var(--spacing)*4+var(--scrollbar-width,0px))] fixed right-[calc(var(--spacing)*3+var(--scrollbar-width,0px))] bottom-8 z-50 sm:right-[calc(var(--spacing)*6+var(--scrollbar-width,0px))] sm:bottom-5 lg:bottom-12 xl:right-[calc(var(--spacing)*12+var(--scrollbar-width,0px))]"
  >
    <Transition
      enter-active-class="transition duration-300 ease-out"
      leave-active-class="transition duration-300 ease-in"
      enter-from-class="translate-y-full opacity-0"
      leave-to-class="translate-y-full opacity-0"
    >
      <button
        v-if="showButton"
        @click="scrollToTop"
        class="text-foreground ring-foreground/15 bg-background/70 pointer-fine:hover:bg-primary pointer-fine:hover:text-primary-foreground flex size-12 items-center justify-center gap-x-1.5 rounded-full text-sm font-semibold tracking-tighter ring-1 backdrop-blur-xs transition-all duration-300"
        v-ripple
      >
        <Icon name="lucide:chevron-up" class="size-4.5 shrink-0" />
      </button>
    </Transition>
  </div>
</template>

<script setup>
const props = defineProps({
  threshold: {
    type: Number,
    default: 300,
  },
});

const showButton = ref(false);

const scrollToTop = () => {
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });
};

const handleScroll = () => {
  showButton.value = window.scrollY > props.threshold;
};

onMounted(() => {
  window.addEventListener("scroll", handleScroll, { passive: true });
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener("scroll", handleScroll);
});
</script>
