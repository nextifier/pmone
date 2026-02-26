<template>
  <nav v-show="headings.length > 0" class="flex flex-col gap-1 py-2">
    <h3
      v-if="showLabel"
      class="text-foreground flex h-7 items-center text-xs font-medium"
    >
      On This Page
    </h3>
    <div ref="listRef" class="relative ml-3.5 flex flex-col gap-0.5">
      <!-- Vertical border line -->
      <div class="border-border absolute inset-y-0 -left-[13px] w-px border-l"></div>

      <!-- Sliding active indicator -->
      <div
        ref="indicatorRef"
        class="bg-primary absolute -left-[13px] w-0.5 rounded-full"
        :style="{
          top: indicatorTop + 'px',
          height: indicatorHeight + 'px',
          opacity: indicatorOpacity,
          transition: 'top 0.26s cubic-bezier(0.215, 0.610, 0.355, 1), height 0.26s cubic-bezier(0.215, 0.610, 0.355, 1), opacity 0.2s ease',
        }"
      ></div>

      <a
        v-for="heading in headings"
        :key="heading.id"
        :ref="(el) => { if (el) linkRefs[heading.id] = el }"
        :href="`#${heading.id}`"
        @click.prevent="scrollToHeading(heading.id)"
        class="relative py-1 text-[13px] leading-[1.125rem] tracking-tight no-underline transition-colors duration-200"
        :class="[
          activeHeadingId === heading.id
            ? 'text-foreground'
            : 'text-muted-foreground hover:text-foreground',
          ['H3', 'H4', 'H5', 'H6'].includes(heading.tag) ? 'pl-3.5' : '',
        ]"
      >
        {{ heading.text }}
      </a>
    </div>
  </nav>
</template>

<script setup>
const props = defineProps({
  showLabel: {
    type: Boolean,
    default: true,
  },
  contentSelector: {
    type: String,
    required: true,
  },
});

const headings = ref([]);
const activeHeadingId = ref(null);
let observer = null;

const listRef = ref(null);
const indicatorRef = ref(null);
const linkRefs = ref({});

const indicatorTop = ref(0);
const indicatorHeight = ref(0);
const indicatorOpacity = ref(0);

import { useSidebar } from "@/components/ui/sidebar/utils";
const { setOpenMobile } = useSidebar();

const scrollToHeading = (id) => {
  const element = document.getElementById(id);
  if (element) {
    element.scrollIntoView({
      behavior: "smooth",
      block: "start",
    });
  }
  setOpenMobile(false);
};

const emit = defineEmits(["headings-found"]);

const updateIndicator = (headingId) => {
  if (!headingId || !linkRefs.value[headingId]) {
    indicatorOpacity.value = 0;
    return;
  }

  const linkEl = linkRefs.value[headingId];
  if (linkEl) {
    indicatorTop.value = linkEl.offsetTop + 1;
    indicatorHeight.value = linkEl.offsetHeight - 2;
    indicatorOpacity.value = 1;
  }
};

watch(activeHeadingId, (newId) => {
  updateIndicator(newId);
});

const createUniqueId = (text, index) => {
  const slug = text
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^\w-]+/g, "");

  return `${slug}-${index}`;
};

onMounted(async () => {
  await nextTick();

  let selector = props.contentSelector;
  if (selector.startsWith("#") && /\d/.test(selector.charAt(1))) {
    selector = `[id="${selector.substring(1)}"]`;
  }

  const contentElement = document.querySelector(selector);
  if (!contentElement) {
    return;
  }

  const headingNodes = contentElement.querySelectorAll("h2, h3, h4, h5, h6");
  const foundHeadings = [];
  headingNodes.forEach((node, index) => {
    const id = createUniqueId(node.innerText, index);
    node.id = id;
    foundHeadings.push({ id, text: node.innerText, tag: node.tagName });
  });

  headings.value = foundHeadings;
  emit("headings-found", headings.value);

  if (headings.value.length === 0) return;

  // Default to first heading after DOM is ready
  await nextTick();
  activeHeadingId.value = headings.value[0].id;

  const observerCallback = (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        activeHeadingId.value = entry.target.id;
      }
    });
  };

  observer = new IntersectionObserver(observerCallback, {
    rootMargin: "-10% 0px -40% 0px",
    threshold: 1.0,
  });

  headings.value.forEach((heading) => {
    const element = document.getElementById(heading.id);
    if (element) {
      observer.observe(element);
    }
  });
});

onUnmounted(() => {
  if (observer) {
    observer.disconnect();
  }
});
</script>
