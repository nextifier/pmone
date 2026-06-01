// Harvest-time parser: a shaders.com preset `.vue` export string -> a structured
// ComponentConfig tree ({ components: [{ type, props, children? }] }) plus the
// root <Shader> colorSpace / toneMapping. The frontend consumes the parsed JSON;
// this script never ships to the browser.
//
// Uses the real Vue template compiler (AST), not regex, so nested effects and
// every prop shape (numbers, arrays, position objects, PropDrivers) parse
// correctly. Function() eval here only runs on trusted shaders.com literal
// exports at harvest time — never on runtime user input.
import { baseParse } from "@vue/compiler-dom";

const NODE_ELEMENT = 1;
const NODE_ATTRIBUTE = 6;
const NODE_DIRECTIVE = 7;

const KEBAB = /-([a-z0-9])/g;
const toCamel = (s) => s.replace(KEBAB, (_, c) => c.toUpperCase());

/** Evaluate a Vue bind expression literal (e.g. "1.7", "-90", "{ x: 0.5, y: 0 }"). */
function evalExpression(expr) {
  const trimmed = (expr ?? "").trim();
  if (trimmed === "") return true;
  try {
    return Function(`"use strict"; return (${trimmed});`)();
  } catch {
    return trimmed;
  }
}

function propsOf(node) {
  const props = {};
  for (const prop of node.props ?? []) {
    if (prop.type === NODE_ATTRIBUTE) {
      props[toCamel(prop.name)] = prop.value ? prop.value.content : true;
    } else if (prop.type === NODE_DIRECTIVE && prop.name === "bind" && prop.arg) {
      props[toCamel(prop.arg.content)] = evalExpression(prop.exp?.content);
    }
  }
  return props;
}

function elementToConfig(node) {
  const config = { type: node.tag, props: propsOf(node) };
  const children = (node.children ?? [])
    .filter((c) => c.type === NODE_ELEMENT)
    .map(elementToConfig);
  if (children.length) config.children = children;
  return config;
}

function findElement(node, tag) {
  if (node.type === NODE_ELEMENT && node.tag === tag) return node;
  for (const child of node.children ?? []) {
    const found = findElement(child, tag);
    if (found) return found;
  }
  return null;
}

/**
 * @param {string} code full `.vue` SFC export string from get-preset
 * @returns {{ components: any[], colorSpace?: string, toneMapping?: string }}
 */
export function parsePresetCode(code) {
  const templateMatch = code.match(/<template>([\s\S]*?)<\/template>/);
  const template = templateMatch ? templateMatch[1] : code;
  const ast = baseParse(template);

  const shader = findElement(ast, "Shader");
  if (!shader) throw new Error("No <Shader> root element found in preset code");

  const shaderProps = propsOf(shader);
  const components = (shader.children ?? [])
    .filter((c) => c.type === NODE_ELEMENT)
    .map(elementToConfig);

  const result = { components };
  if (shaderProps.colorSpace) result.colorSpace = shaderProps.colorSpace;
  if (shaderProps.toneMapping) result.toneMapping = shaderProps.toneMapping;
  return result;
}

// --- self test: `node parse-preset.mjs --selftest` ---
if (process.argv.includes("--selftest")) {
  const sample = `<script setup lang="ts">
import { Shader, ChromaFlow, FilmGrain, FlutedGlass, Swirl } from 'shaders/vue'
</script>

<template>
  <Shader>
    <Swirl color-a="#000000" color-b="#0a0a0a" :detail="1.7"/>
    <ChromaFlow base-color="#18181a" down-color="#05ffa1" left-color="#d100ff" :momentum="13" right-color="#39ff14" up-color="#ff2a6d"/>
    <FlutedGlass :aberration="0.61" :angle="250" :frequency="8" :highlight="0.12" :highlight-softness="0" :light-angle="-90" :refraction="4" shape="rounded" :softness="1" :speed="0.15"/>
    <FilmGrain :strength="0.05"/>
  </Shader>
</template>`;

  const out = parsePresetCode(sample);
  console.log(JSON.stringify(out, null, 2));

  const expected = {
    components: [
      { type: "Swirl", props: { colorA: "#000000", colorB: "#0a0a0a", detail: 1.7 } },
      {
        type: "ChromaFlow",
        props: {
          baseColor: "#18181a",
          downColor: "#05ffa1",
          leftColor: "#d100ff",
          momentum: 13,
          rightColor: "#39ff14",
          upColor: "#ff2a6d",
        },
      },
      {
        type: "FlutedGlass",
        props: {
          aberration: 0.61,
          angle: 250,
          frequency: 8,
          highlight: 0.12,
          highlightSoftness: 0,
          lightAngle: -90,
          refraction: 4,
          shape: "rounded",
          softness: 1,
          speed: 0.15,
        },
      },
      { type: "FilmGrain", props: { strength: 0.05 } },
    ],
  };

  const ok = JSON.stringify(out) === JSON.stringify(expected);
  console.log(ok ? "\n✅ SELFTEST PASS" : "\n❌ SELFTEST FAIL");
  process.exit(ok ? 0 : 1);
}
