<template>
  <Tabs
    :model-value="modelValue"
    variant="segmented"
    class="self-start"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <TabsList>
      <TabsIndicator />
      <TabsTrigger v-for="locale in WEBSITE_LOCALES" :key="locale.value" :value="locale.value">
        {{ locale.label }}
      </TabsTrigger>
    </TabsList>
  </Tabs>
</template>

<script setup>
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";

// Shared locale switcher for per-locale website content editors (SEO meta,
// legal pages). WEBSITE_LOCALES is auto-imported from app/utils/websiteLocales.
defineProps({
  modelValue: { type: String, default: "en" },
});

defineEmits(["update:modelValue"]);
</script>
