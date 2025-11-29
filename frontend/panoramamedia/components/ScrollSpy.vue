<template>
  <nav v-show="headings.length > 0" class="space-y-2">
    <h3
      v-if="showLabel"
      class="text-primary text-sm font-semibold tracking-tighter"
    >
      On this page
    </h3>
    <ul ref="listRef" class="relative -ml-4 tracking-tight">
      <div
        ref="indicatorRef"
        class="bg-primary absolute top-[var(--indicator-top,0px)] left-0 h-[var(--indicator-height,0px)] w-px rounded-full opacity-[var(--indicator-opacity)] transition-all duration-300 ease-out"
      ></div>

      <li v-for="heading in headings" :key="heading.id">
        <a
          :ref="(el) => (linkRefs[heading.id] = el)"
          :href="`#${heading.id}`"
          @click.prevent="scrollToHeading(heading.id)"
          class="block py-1 pl-4 text-sm !leading-normal font-medium transition-all"
          :class="[
            {
              'text-primary': activeHeadingId === heading.id,
              'text-muted-foreground hover:text-primary':
                activeHeadingId !== heading.id,
              'pl-7': ['H3', 'H4', 'H5', 'H6'].includes(heading.tag),
            },
          ]"
        >
          {{ heading.text }}
        </a>
      </li>
    </ul>
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

watch(activeHeadingId, (newActiveId) => {
  const indicatorEl = indicatorRef.value;
  if (newActiveId && indicatorEl) {
    const activeLinkEl = linkRefs.value[newActiveId];
    if (activeLinkEl) {
      indicatorEl.style.setProperty(
        "--indicator-top",
        `${activeLinkEl.offsetTop}px`,
      );
      indicatorEl.style.setProperty(
        "--indicator-height",
        `${activeLinkEl.offsetHeight}px`,
      );
      indicatorEl.style.setProperty("--indicator-opacity", "1");
    }
  } else if (indicatorEl && !newActiveId && headings.value.length > 0) {
    const firstLinkEl = linkRefs.value[headings.value[0].id];
    if (firstLinkEl) {
      indicatorEl.style.setProperty(
        "--indicator-top",
        `${firstLinkEl.offsetTop}px`,
      );
      indicatorEl.style.setProperty(
        "--indicator-height",
        `${firstLinkEl.offsetHeight}px`,
      );
    }
    indicatorEl.style.setProperty("--indicator-opacity", "0");
  }
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
