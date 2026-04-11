<script setup>
const SOCIAL_PREFIXES = {
  Website: "https://",
  Instagram: "https://instagram.com/",
  Facebook: "https://facebook.com/",
  X: "https://x.com/",
  TikTok: "https://tiktok.com/@",
  LinkedIn: "https://linkedin.com/in/",
  YouTube: "https://youtube.com/@",
};

// Alternative domains/patterns to detect when user pastes a full URL
const SOCIAL_PATTERNS = {
  Instagram: [/(?:https?:\/\/)?(?:www\.)?instagram\.com\//i],
  Facebook: [/(?:https?:\/\/)?(?:www\.)?(?:facebook|fb)\.com\//i],
  X: [/(?:https?:\/\/)?(?:www\.)?(?:x|twitter)\.com\//i],
  TikTok: [/(?:https?:\/\/)?(?:www\.)?tiktok\.com\/@?/i],
  LinkedIn: [/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\//i],
  YouTube: [/(?:https?:\/\/)?(?:www\.)?youtube\.com\/@?/i, /(?:https?:\/\/)?youtu\.be\//i],
};

const props = defineProps({
  modelValue: { type: String, default: "" },
  label: { type: String, default: "" },
  class: { type: [String, Object, Array], default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const prefix = computed(() => SOCIAL_PREFIXES[props.label] || "https://");
const isSocial = computed(() => props.label in SOCIAL_PREFIXES && props.label !== "Website");
const placeholder = computed(() => (isSocial.value ? "username" : "example.com"));

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
</script>

<template>
  <InputGroup :class="props.class">
    <InputGroupAddon>
      <InputGroupText>{{ prefix }}</InputGroupText>
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
