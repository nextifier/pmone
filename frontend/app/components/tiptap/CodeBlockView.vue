<template>
  <NodeViewWrapper class="bg-muted/50 relative rounded-lg">
    <div contenteditable="false" class="absolute -top-9 right-0">
      <Select :model-value="selectedLanguage" @update:model-value="updateLanguage">
        <SelectTrigger
          size="sm"
          class="bg-muted/50 border-border h-6 w-auto gap-1.5 border px-2 text-xs shadow-none"
        >
          <SelectValue placeholder="plain text" />
        </SelectTrigger>
        <SelectContent align="end" class="min-w-32">
          <SelectItem value="plain">plain text</SelectItem>
          <SelectSeparator />
          <SelectItem v-for="lang in LANGUAGES" :key="lang" :value="lang">
            {{ lang }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <pre
      class="overflow-x-auto bg-transparent! p-4 text-sm [&>code]:bg-transparent!"
    ><NodeViewContent as="code" /></pre>
  </NodeViewWrapper>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useShiki } from "@/composables/useShiki";
import { NodeViewContent, NodeViewWrapper } from "@tiptap/vue-3";

const { LANGUAGES } = useShiki();

const props = defineProps({
  node: { type: Object, required: true },
  updateAttributes: { type: Function, required: true },
  editor: { type: Object, required: true },
  getPos: { type: Function, required: true },
});

const selectedLanguage = computed(() => props.node.attrs.language || "plain");

const updateLanguage = (value) => {
  props.updateAttributes({ language: value === "plain" ? null : value });
};
</script>
