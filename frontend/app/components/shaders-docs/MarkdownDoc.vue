<script setup>
import { computed } from "vue";
import { marked } from "marked";
import { parse as parseYaml } from "yaml";
import CodeBlock from "@/components/ui-docs/CodeBlock.vue";
import ShaderCanvas from "@/components/shaders/ShaderCanvas.vue";
import PropTable from "./PropTable.vue";

const props = defineProps({
  source: {
    type: String,
    required: true,
  },
  // Component being documented - used for bare ::props-table / ::shader-preview.
  componentName: {
    type: String,
    default: "",
  },
});

function renderMd(text) {
  return marked.parse(text.trim(), { gfm: true });
}

function normalizeLang(lang) {
  const l = (lang || "").toLowerCase();
  if (l === "vue-html" || l === "html") return "vue";
  if (l === "javascript") return "js";
  if (l === "typescript") return "ts";
  return l || "text";
}

function extractVueBlock(content) {
  const vue = content.match(/```(?:vue-html|vue)\r?\n([\s\S]*?)```/);
  if (vue) return vue[1].replace(/\s+$/, "");
  const first = content.match(/```\S*\r?\n([\s\S]*?)```/);
  return first ? first[1].replace(/\s+$/, "") : null;
}

function parseDemo(content) {
  const fenced = content.match(/---\r?\n([\s\S]*?)\r?\n---/);
  const yamlText = fenced ? fenced[1] : content;
  try {
    const data = parseYaml(yamlText);
    const components = data?.preset?.components ?? data?.components;
    if (Array.isArray(components)) return { components };
  } catch {
    /* ignore malformed demo */
  }
  return null;
}

function parseContainer(name, attrs, content) {
  if (name === "shader-preview") {
    const match = attrs.match(/component=["']([^"']+)["']/);
    const component = match?.[1] || props.componentName;
    return component ? { kind: "preview", component } : null;
  }
  if (name === "props-table") {
    return props.componentName ? { kind: "props", component: props.componentName } : null;
  }
  if (name === "code-group") {
    const code = extractVueBlock(content);
    return code ? { kind: "code", lang: "vue", code } : null;
  }
  if (name === "shader-demo") {
    const config = parseDemo(content);
    return config ? { kind: "demo", config } : null;
  }
  return { kind: "md", html: renderMd(content) };
}

const segments = computed(() => {
  const lines = props.source.split(/\r?\n/);
  const out = [];
  let buffer = [];

  const flush = () => {
    const text = buffer.join("\n").trim();
    if (text) out.push({ kind: "md", html: renderMd(text) });
    buffer = [];
  };

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    const fence = line.match(/^```(\S+)?/);
    if (fence) {
      flush();
      const code = [];
      i++;
      while (i < lines.length && !/^```/.test(lines[i])) code.push(lines[i++]);
      out.push({ kind: "code", lang: normalizeLang(fence[1]), code: code.join("\n") });
      continue;
    }

    const open = line.match(/^::([\w-]+)(\{.*\})?\s*$/);
    if (open) {
      flush();
      const content = [];
      i++;
      while (i < lines.length && lines[i].trim() !== "::") content.push(lines[i++]);
      const seg = parseContainer(open[1], open[2] || "", content.join("\n"));
      if (seg) out.push(seg);
      continue;
    }

    buffer.push(line);
  }
  flush();
  return out;
});
</script>

<template>
  <div class="space-y-5">
    <template v-for="(seg, i) in segments" :key="i">
      <div v-if="seg.kind === 'md'" class="format-html" v-html="seg.html" />
      <CodeBlock v-else-if="seg.kind === 'code'" :code="seg.code" :language="seg.lang" />
      <PropTable v-else-if="seg.kind === 'props'" :name="seg.component" />
      <div
        v-else-if="seg.kind === 'preview'"
        class="ring-border aspect-video overflow-hidden rounded-xl ring-1"
      >
        <ShaderCanvas :config="{ components: [{ type: seg.component, props: {} }] }" class="size-full" />
      </div>
      <div
        v-else-if="seg.kind === 'demo'"
        class="ring-border aspect-video overflow-hidden rounded-xl ring-1"
      >
        <ShaderCanvas :config="seg.config" class="size-full" />
      </div>
    </template>
  </div>
</template>
