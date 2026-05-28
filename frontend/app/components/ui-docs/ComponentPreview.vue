<template>
  <Tabs default-value="preview" variant="segmented" class="w-full">
    <TabsList>
      <TabsIndicator />
      <TabsTrigger value="preview">Preview</TabsTrigger>
      <TabsTrigger value="code">Code</TabsTrigger>
    </TabsList>

    <TabsContent value="preview" :class="containerClass">
      <slot />
    </TabsContent>

    <TabsContent value="code" class="mt-3">
      <CodeBlock :code="code" :language="language" />
    </TabsContent>
  </Tabs>
</template>

<script setup>
import { Tabs, TabsContent, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import CodeBlock from "@/components/ui-docs/CodeBlock.vue";

const props = defineProps({
  code: {
    type: String,
    required: true,
  },
  language: {
    type: String,
    default: "vue",
  },
  align: {
    type: String,
    default: "start",
    validator: (v) => ["start", "center"].includes(v),
  },
});

const containerClass = computed(() => [
  "mt-3 bg-background rounded-2xl border p-4 sm:p-8 flex flex-wrap gap-3",
  props.align === "center" ? "items-center justify-center min-h-40" : "items-start",
]);
</script>
