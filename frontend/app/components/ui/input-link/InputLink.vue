<script setup>
const SOCIAL_PREFIXES = {
  Website: "https://",
  Instagram: "https://instagram.com/",
  Facebook: "https://facebook.com/",
  X: "https://x.com/",
  TikTok: "https://tiktok.com/@",
  LinkedIn: "https://linkedin.com/company/",
  YouTube: "https://youtube.com/@",
};

// Patterns matching the canonical predefined sub-path (used to extract username)
const SOCIAL_SUB_PATTERNS = {
  Instagram: [/^(?:https?:\/\/)?(?:www\.)?instagram\.com\//i],
  Facebook: [/^(?:https?:\/\/)?(?:www\.)?(?:facebook|fb)\.com\//i],
  X: [/^(?:https?:\/\/)?(?:www\.)?(?:x|twitter)\.com\//i],
  TikTok: [/^(?:https?:\/\/)?(?:www\.)?tiktok\.com\/@?/i],
  LinkedIn: [/^(?:https?:\/\/)?(?:www\.)?linkedin\.com\/company\//i],
  YouTube: [/^(?:https?:\/\/)?(?:www\.)?youtube\.com\/@?/i, /^(?:https?:\/\/)?youtu\.be\//i],
};

// Broader domain patterns to detect platform regardless of sub-path
const SOCIAL_DOMAIN_PATTERNS = {
  Instagram: /^(?:https?:\/\/)?(?:www\.)?instagram\.com(?:\/|$)/i,
  Facebook: /^(?:https?:\/\/)?(?:www\.)?(?:facebook|fb)\.com(?:\/|$)/i,
  X: /^(?:https?:\/\/)?(?:www\.)?(?:x|twitter)\.com(?:\/|$)/i,
  TikTok: /^(?:https?:\/\/)?(?:www\.)?tiktok\.com(?:\/|$)/i,
  LinkedIn: /^(?:https?:\/\/)?(?:www\.)?linkedin\.com(?:\/|$)/i,
  YouTube: /^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)(?:\/|$)/i,
};

const props = defineProps({
  modelValue: { type: String, default: "" },
  label: { type: String, default: "" },
  class: { type: [String, Object, Array], default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const socialPrefix = computed(() => SOCIAL_PREFIXES[props.label] || "https://");
const isSocial = computed(() => props.label in SOCIAL_PREFIXES && props.label !== "Website");

// Full URL mode: modelValue is a platform URL that doesn't fit the canonical prefix
// (e.g. LinkedIn company/school URL while label is LinkedIn)
const isFullUrlMode = computed(() => {
  if (!isSocial.value) return false;
  if (!props.modelValue) return false;
  return !props.modelValue.toLowerCase().startsWith(socialPrefix.value.toLowerCase());
});

const displayPrefix = computed(() => (isFullUrlMode.value ? "https://" : socialPrefix.value));
const placeholder = computed(() => (isSocial.value && !isFullUrlMode.value ? "username" : "example.com"));

const displayValue = computed(() => {
  if (!props.modelValue) return "";
  if (isSocial.value && !isFullUrlMode.value) {
    return props.modelValue.slice(socialPrefix.value.length).replace(/\/+$/, "");
  }
  return props.modelValue
    .replace(/^https?:\/\//i, "")
    .replace(/^www\./i, "")
    .replace(/\/+$/, "");
});

function processInput(raw) {
  const str = String(raw ?? "").trim();
  if (!str) return "";

  if (isSocial.value) {
    // 1. Matches canonical sub-path (e.g. linkedin.com/in/) -> extract username
    const subPatterns = SOCIAL_SUB_PATTERNS[props.label] || [];
    for (const pat of subPatterns) {
      if (pat.test(str)) {
        const username = str.replace(pat, "").replace(/\/+$/, "");
        return username ? socialPrefix.value + username : "";
      }
    }

    // 2. Matches platform domain but different sub-path (e.g. linkedin.com/company/) -> store full URL
    const domainPat = SOCIAL_DOMAIN_PATTERNS[props.label];
    if (domainPat && domainPat.test(str)) {
      const clean = str.replace(/^https?:\/\//i, "").replace(/^www\./i, "").replace(/\/+$/, "");
      return clean ? "https://" + clean : "";
    }
  }

  // 3. Plain text (username for social, or domain for Website)
  const cleaned = str
    .replace(/^https?:\/\//i, "")
    .replace(/^www\./i, "")
    .replace(/\/+$/, "");
  if (!cleaned) return "";

  if (isSocial.value) {
    return socialPrefix.value + cleaned;
  }
  return "https://" + cleaned;
}

function onValueChange(value) {
  emit("update:modelValue", processInput(value));
}

function onPaste(e) {
  e.preventDefault();
  emit("update:modelValue", processInput(e.clipboardData.getData("text")));
}

// When label changes, rebuild URL with new prefix
watch(
  () => props.label,
  () => {
    if (!props.modelValue) return;
    emit("update:modelValue", processInput(props.modelValue));
  }
);
</script>

<template>
  <InputGroup :class="props.class">
    <InputGroupAddon>
      <InputGroupText>{{ displayPrefix }}</InputGroupText>
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
