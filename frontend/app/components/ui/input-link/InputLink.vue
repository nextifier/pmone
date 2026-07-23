<script setup>
const SOCIAL_PREFIXES = {
  Website: "https://",
  Instagram: "https://instagram.com/",
  Facebook: "https://facebook.com/",
  X: "https://x.com/",
  TikTok: "https://tiktok.com/@",
  // Keep the path segment in the value: LinkedIn has two namespaces
  // (`/in/<person>` and `/company/<page>`), so the user types e.g.
  // "company/megabuildid" or "in/john-doe" after this prefix.
  LinkedIn: "https://linkedin.com/",
  YouTube: "https://youtube.com/@",
};

// Alternative domains/patterns to detect when user pastes a full URL
const SOCIAL_PATTERNS = {
  Instagram: [/(?:https?:\/\/)?(?:www\.)?instagram\.com\//i],
  Facebook: [/(?:https?:\/\/)?(?:www\.)?(?:facebook|fb)\.com\//i],
  X: [/(?:https?:\/\/)?(?:www\.)?(?:x|twitter)\.com\//i],
  TikTok: [/(?:https?:\/\/)?(?:www\.)?tiktok\.com\/@?/i],
  // Match the domain only so the `/in/` or `/company/` path stays in the value.
  LinkedIn: [/(?:https?:\/\/)?(?:www\.)?linkedin\.com\//i],
  YouTube: [/(?:https?:\/\/)?(?:www\.)?youtube\.com\/@?/i, /(?:https?:\/\/)?youtu\.be\//i],
};

const props = defineProps({
  modelValue: { type: String, default: "" },
  label: { type: String, default: "" },
  class: { type: [String, Object, Array], default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const SOCIAL_PLACEHOLDERS = {
  LinkedIn: "company/name or in/username",
};

const prefix = computed(() => SOCIAL_PREFIXES[props.label] || "https://");
const isSocial = computed(() => props.label in SOCIAL_PREFIXES && props.label !== "Website");
const placeholder = computed(
  () => SOCIAL_PLACEHOLDERS[props.label] || (isSocial.value ? "username" : "example.com"),
);

function stripPrefix(url) {
  if (!url) return "";

  // For social media, try to extract username/path from known URL patterns
  if (isSocial.value) {
    const patterns = SOCIAL_PATTERNS[props.label] || [];
    for (const pattern of patterns) {
      if (pattern.test(url)) {
        return url.replace(pattern, "").replace(/\/+$/, "");
      }
    }
  }

  // For general URLs, strip protocol and www
  return url
    .replace(/^https?:\/\//i, "")
    .replace(/^www\./i, "")
    .replace(/\/+$/, "");
}

function buildFullUrl(input) {
  if (!input) return "";
  return prefix.value + input;
}

// Display value - strip prefix from full URL
const displayValue = computed(() => stripPrefix(props.modelValue));

function onValueChange(value) {
  const str = String(value ?? "");
  const cleaned = stripPrefix(str);
  emit("update:modelValue", cleaned ? buildFullUrl(cleaned) : "");
}

function onPaste(e) {
  e.preventDefault();
  const pasted = e.clipboardData.getData("text").trim();
  const cleaned = stripPrefix(pasted);
  emit("update:modelValue", cleaned ? buildFullUrl(cleaned) : "");
}

// When label changes, rebuild URL with new prefix
watch(
  () => props.label,
  () => {
    if (!props.modelValue) return;
    const core = displayValue.value;
    if (core) {
      emit("update:modelValue", buildFullUrl(core));
    }
  }
);

// Keep the prefix scrolled to its END so the join point (the "/" or trailing "@" the
// user types after) is what shows by default; the hidden head (protocol) is revealed by
// scrolling left. Re-run on mount, when the prefix changes, and when the field resizes.
const prefixRef = ref(null);
function scrollPrefixToEnd() {
  const el = prefixRef.value;
  if (el) el.scrollLeft = el.scrollWidth;
}
watch(prefix, () => nextTick(scrollPrefixToEnd));
onMounted(() => {
  nextTick(scrollPrefixToEnd);
  if (typeof ResizeObserver === "undefined" || !prefixRef.value) return;
  const observer = new ResizeObserver(() => scrollPrefixToEnd());
  observer.observe(prefixRef.value);
  onBeforeUnmount(() => observer.disconnect());
});
</script>

<template>
  <InputGroup :class="props.class">
    <!-- The prefix is fixed context, so it yields space to what the user actually types:
         it is capped at 45% width so the input keeps a usable width on narrow/mobile
         screens; on wide fields it shows in full.
         When it overflows, the prefix scrolls (no scrollbar) instead of being clipped, so
         nothing is permanently hidden. It is scrolled to its END (see scrollPrefixToEnd),
         so the join point shows by default — the user sees the character their input
         attaches after ("/" or a trailing "@"), which prevents e.g. typing "@handle" after
         a prefix that already ends in "@". `scroll-fade-x` fades whichever edge still has
         hidden content: only the start (protocol) at rest, and the end once the user
         scrolls left, so there is always a hint toward the content that is off-screen. -->
    <InputGroupAddon class="min-w-0 max-w-[45%]">
      <span
        ref="prefixRef"
        class="cn-input-group-text no-scrollbar scroll-fade-x block min-w-0 overflow-x-auto whitespace-nowrap"
        >{{ prefix }}</span
      >
    </InputGroupAddon>
    <InputGroupInput
      :model-value="displayValue"
      @update:model-value="onValueChange"
      @paste="onPaste"
      :placeholder="placeholder"
      autocapitalize="none"
      class="pl-0!"
    />
    <InputGroupAddon align="inline-end" class="has-[>a]:mr-[-0.45rem]">
      <Button
        :to="modelValue || undefined"
        variant="ghost"
        :disabled="!modelValue"
        class="size-6 rounded-[calc(var(--radius)-5px)] p-0 text-sm shadow-none"
      >
        <Icon name="hugeicons:arrow-up-right-01" class="size-3.5" />
      </Button>
    </InputGroupAddon>
  </InputGroup>
</template>
