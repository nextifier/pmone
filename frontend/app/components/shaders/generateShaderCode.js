// Our own preset -> `.vue` code generator. We deliberately do NOT use the
// package's `shaders/vue/codegen` (a single huge module that overflows a Vite
// plugin's regex code-filter - see the shaders-vite-integration memory). Writing
// it ourselves also lets us guarantee `:disable-telemetry="true"` is always
// present in the copyable code, matching the project rule.

const CAMEL_BOUNDARY = /([a-z0-9])([A-Z])/g;
const toKebab = (s) => s.replace(CAMEL_BOUNDARY, "$1-$2").toLowerCase();

function jsLiteral(value) {
  if (Array.isArray(value)) {
    return `[${value.map(jsLiteral).join(", ")}]`;
  }
  if (value && typeof value === "object") {
    const body = Object.entries(value)
      .map(([k, v]) => `${k}: ${jsLiteral(v)}`)
      .join(", ");
    return `{ ${body} }`;
  }
  if (typeof value === "string") {
    return `'${value}'`;
  }
  return String(value);
}

function attr(name, value) {
  const kebab = toKebab(name);
  if (typeof value === "string") {
    return `${kebab}="${value}"`;
  }
  if (typeof value === "number" || typeof value === "boolean") {
    return `:${kebab}="${value}"`;
  }
  // objects / arrays / PropDrivers
  return `:${kebab}="${jsLiteral(value)}"`;
}

function emitNode(node, indent, usedTypes) {
  usedTypes.add(node.type);
  const pad = "  ".repeat(indent);
  const attrs = Object.entries(node.props ?? {}).map(([k, v]) => attr(k, v));
  const children = (node.children ?? []).filter(Boolean);

  const attrStr = attrs.length ? ` ${attrs.join(" ")}` : "";

  if (!children.length) {
    return `${pad}<${node.type}${attrStr} />`;
  }
  const inner = children.map((child) => emitNode(child, indent + 1, usedTypes)).join("\n");
  return `${pad}<${node.type}${attrStr}>\n${inner}\n${pad}</${node.type}>`;
}

/**
 * @param {{ components: any[] }} config
 * @param {{ colorSpace?: string, toneMapping?: string }} [opts]
 * @returns {string} a copy-pasteable Vue SFC string
 */
export function generateShaderCode(config, opts = {}) {
  const usedTypes = new Set();
  const components = config?.components ?? [];
  const body = components.map((node) => emitNode(node, 2, usedTypes)).join("\n");

  const imports = ["Shader", ...[...usedTypes].sort()].join(",\n  ");

  const shaderAttrs = ['    :disable-telemetry="true"'];
  if (opts.colorSpace && opts.colorSpace !== "p3-linear") {
    shaderAttrs.push(`    color-space="${opts.colorSpace}"`);
  }
  if (opts.toneMapping && opts.toneMapping !== "aces") {
    shaderAttrs.push(`    tone-mapping="${opts.toneMapping}"`);
  }

  return `<script setup lang="ts">
import {
  ${imports}
} from 'shaders/vue'
</script>

<template>
  <Shader
${shaderAttrs.join("\n")}
  >
${body}
  </Shader>
</template>
`;
}
