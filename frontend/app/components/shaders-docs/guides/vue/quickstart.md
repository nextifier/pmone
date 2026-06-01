---
title: Quickstart
description: Install Shaders and build your first GPU-accelerated effect with Vue
icon: rocket
category: concepts
---

# Quickstart (Vue)

## Install the npm package

```bash
npm install shaders
```

## Import some components

```javascript
import { Shader, LinearGradient, CursorTrail } from 'shaders/vue'
```

## Your first shader

Let's create a very simple shader:

```vue-html
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```

::shader-demo
---
preset:
  components:
    - type: LinearGradient
      props:
        colorA: "#0f172a"
        colorB: "#7c3aed"
    - type: CursorTrail
---
::

`<Shader>` renders a `<canvas>` element. Child components are visual layers evaluated top to bottom, blended together on the GPU. In this example, we render the linear gradient first, and then the cursor trail effect on top of that.

## Sizing the canvas

The `<canvas>` has no intrinsic size. The class or style you apply to `<Shader>` controls the canvas size and position.

```vue-html
<!-- Fill the viewport -->
<Shader class="w-screen h-screen">
  <LinearGradient />
</Shader>

<!-- Fixed dimensions -->
<Shader style="width: 600px; height: 400px;">
  <LinearGradient />
</Shader>

<!-- Fluid with aspect ratio -->
<Shader class="w-full aspect-video">
  <LinearGradient />
</Shader>
```

Applying a class or style to the `<Shader>` tag is preferred over trying to target the `<canvas>` element directly, as the internal DOM structure may change in the future.

## Configuring components

Pass props directly to configure each component. Numeric props use Vue's `:` binding syntax, strings can be passed plain:

```vue-html
<Shader class="w-full h-64">
  <LinearGradient
    color-a="#ff6b6b"
    color-b="#4ecdc4"
    :angle="45"
    color-space="oklch"
  />
</Shader>
```

Props also accept reactive state - refs and computed values work exactly as you'd expect:

```vue
<script setup>
import { ref } from 'vue'
import { Shader, LinearGradient } from 'shaders/vue'

const angle = ref(0)
</script>

<template>
  <Shader class="w-full h-64">
    <LinearGradient color-a="#ff6b6b" color-b="#4ecdc4" :angle="angle" />
  </Shader>

  <input type="range" min="0" max="360" v-model.number="angle" />
</template>
```

When `angle` changes, the shader updates on the GPU instantly - no recompilation, no flicker.

Browse the [Component Docs](/docs/components) for the full prop reference on every component.

## Using with Nuxt / SSR

Shaders only works client-side, as it needs a GPU to run. See the [Nuxt / SSR](/docs/guide/vue/ssr) guide for how to set it up with server-side rendering.

## Next steps
