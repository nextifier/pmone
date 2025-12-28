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
      <GlassButton v-if="showButton" @click="scrollToTop" size="icon">
        <Icon name="lucide:arrow-up" class="size-4.5 shrink-0" />
      </GlassButton>
    </Transition>
  </div>
</template>

<script setup>
const props = defineProps({
  threshold: {
    type: Number,
    default: 1000,
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
