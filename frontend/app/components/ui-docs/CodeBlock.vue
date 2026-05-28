<template>
  <div class="ui-code-block bg-card relative overflow-hidden rounded-xl border">
    <div
      class="border-border/60 bg-muted/30 flex items-center gap-x-2 border-b px-3 py-1.5"
    >
      <Icon name="hugeicons:source-code" class="text-muted-foreground size-4" />
      <span class="text-muted-foreground text-sm tracking-tight">{{ language }}</span>
      <ButtonCopy :text="code" class="ml-auto" />
    </div>

    <div
      v-if="html"
      class="overflow-x-auto p-4 text-sm leading-relaxed"
      v-html="html"
    />
    <pre
      v-else
      class="text-foreground overflow-x-auto p-4 font-mono text-sm leading-relaxed tracking-tight"
    ><code>{{ code }}</code></pre>
  </div>
</template>

<script setup>
import { ButtonCopy } from "@/components/ui/button-copy";

const props = defineProps({
  code: {
    type: String,
    required: true,
  },
  language: {
    type: String,
    default: "vue",
  },
});

const { highlighter } = useShiki();
const html = ref("");

watchEffect(() => {
  if (!highlighter.value || !props.code) {
    html.value = "";
    return;
  }
  try {
    html.value = highlighter.value.codeToHtml(props.code, {
      lang: props.language,
      themes: { light: "github-light", dark: "github-dark" },
      defaultColor: false,
    });
  } catch {
    html.value = "";
  }
});
</script>

<style>
.ui-code-block pre.shiki,
.ui-code-block pre.shiki code {
  background-color: transparent !important;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  letter-spacing: -0.005em;
}

.ui-code-block pre.shiki,
.ui-code-block pre.shiki span {
  color: var(--shiki-light);
}

.dark .ui-code-block pre.shiki,
.dark .ui-code-block pre.shiki span {
  color: var(--shiki-dark);
}

.ui-code-block pre.shiki code {
  counter-reset: line;
  display: grid;
}

.ui-code-block pre.shiki .line {
  counter-increment: line;
  padding-left: 2.5rem;
  position: relative;
}

.ui-code-block pre.shiki .line::before {
  content: counter(line);
  position: absolute;
  left: 0;
  width: 2rem;
  text-align: right;
  color: var(--color-muted-foreground);
  opacity: 0.45;
  user-select: none;
  font-variant-numeric: tabular-nums;
}
</style>
