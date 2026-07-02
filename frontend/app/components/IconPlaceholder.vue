<script setup lang="ts">
import { computed } from "vue";

/**
 * Lucide-only shim for the ported `/create` showcase.
 *
 * The upstream `IconPlaceholder` swapped between 5 icon libraries; pmone dropped
 * the Icon Library picker, so we honor only the `lucide` prop and render it via
 * @nuxt/icon (`@iconify-json/lucide` is installed, `icon.clientBundle:false` keeps
 * build memory low). The other library props are accepted (so showcase markup is
 * untouched) but ignored. `class`/`style`/aria attrs fall through to <Icon> as the
 * single root element (Vue attr inheritance).
 */
const props = defineProps<{
  lucide?: string;
  tabler?: string;
  hugeicons?: string;
  phosphor?: string;
  remixicon?: string;
}>();

/** "XIcon" → "x", "ChevronDownIcon" → "chevron-down", "MoreHorizontalIcon" → "more-horizontal". */
function toIconifyName(name?: string): string {
  if (!name) {
    return "square";
  }
  const kebab = name
    .replace(/Icon$/, "")
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/([A-Z])([A-Z][a-z])/g, "$1-$2")
    .replace(/([a-z])([0-9])/g, "$1-$2")
    .toLowerCase();
  return kebab || "square";
}

const iconName = computed(() => `lucide:${toIconifyName(props.lucide)}`);
</script>

<template>
  <Icon :name="iconName" />
</template>
