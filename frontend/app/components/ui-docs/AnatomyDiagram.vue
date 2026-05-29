<script setup>
import CodeBlock from "./CodeBlock.vue";

const props = defineProps({
  tree: {
    type: Array,
    required: true,
  },
  importPath: {
    type: String,
    default: "",
  },
});

const isSlot = (name) => name.startsWith("#");
const slotName = (name) => name.replace(/^#/, "").replace(/\s*slot$/i, "").trim();

function collectImports(nodes, set) {
  for (const node of nodes) {
    if (!isSlot(node.component)) {
      set.add(node.component);
    }
    if (node.children?.length) {
      collectImports(node.children, set);
    }
  }
  return set;
}

function renderNodes(nodes, indent) {
  const pad = "  ".repeat(indent);
  return nodes
    .map((node) => {
      if (isSlot(node.component)) {
        return `${pad}<template #${slotName(node.component)} />`;
      }
      if (node.children?.length) {
        return `${pad}<${node.component}>\n${renderNodes(node.children, indent + 1)}\n${pad}</${node.component}>`;
      }
      return `${pad}<${node.component} />`;
    })
    .join("\n");
}

const code = computed(() => {
  const imports = [...collectImports(props.tree, new Set())].sort();
  const importBlock = imports.length
    ? `import {\n${imports.map((name) => `  ${name},`).join("\n")}\n} from "${props.importPath}";`
    : "";
  const template = renderNodes(props.tree, 1);
  return `<script setup>\n${importBlock}\n<\/script>\n\n<template>\n${template}\n<\/template>`;
});
</script>

<template>
  <CodeBlock :code="code" language="vue" />
</template>
