---
title: Nuxt / SSR
description: Using Shaders in Nuxt and Vue SSR applications
icon: server
category: advanced
---

# SSR with Nuxt / Vue

Shaders uses WebGPU, which requires a browser environment. It cannot run during server-side rendering. This page covers how to safely use Shaders in SSR-enabled Vue applications.

## Nuxt: `<ClientOnly>`

Nuxt provides a built-in `<ClientOnly>` component that prevents its children from rendering on the server. This is the simplest approach:

```vue-html
<ClientOnly>
  <Shader class="w-full h-64">
    <LinearGradient />
  </Shader>
</ClientOnly>
```

The shader renders nothing on the server and mounts on the client after hydration. Your page structure and layout are not affected.

You can add a placeholder slot to show something while the client-side shader loads:

```vue-html
<ClientOnly>
  <Shader class="w-full h-64">
    <LinearGradient />
  </Shader>

  <template #fallback>
    <div class="w-full h-64 bg-gray-900 animate-pulse rounded" />
  </template>
</ClientOnly>
```

## Vue SSR (without Nuxt)

If you're using Vue SSR without Nuxt, use `v-if` with a mounted flag:

```vue
<script setup>
import { ref, onMounted } from 'vue'

const isMounted = ref(false)
onMounted(() => { isMounted.value = true })
</script>

<template>
  <div class="w-full h-64">
    <Shader v-if="isMounted" class="w-full h-64">
      <LinearGradient />
    </Shader>
  </div>
</template>
```

## Dynamic import for code-splitting

To avoid loading the Shaders bundle during SSR and defer it to the client, use a lazy dynamic import:

```vue
<script setup>
import { defineAsyncComponent, ref, onMounted } from 'vue'

const isMounted = ref(false)
onMounted(() => { isMounted.value = true })

// Lazy-load shader components - only fetched client-side when rendered
const Shader = defineAsyncComponent(() => import('shaders/vue').then(m => m.Shader))
const LinearGradient = defineAsyncComponent(() => import('shaders/vue').then(m => m.LinearGradient))
</script>

<template>
  <Shader v-if="isMounted">
    <LinearGradient />
  </Shader>
</template>
```

Or in Nuxt, use `defineNuxtPlugin` or a client-only plugin to lazy-load:

```javascript
// plugins/shaders.client.ts
import { defineNuxtPlugin } from '#app'
import { Shader, LinearGradient } from 'shaders/vue'

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.component('Shader', Shader)
  nuxtApp.vueApp.component('LinearGradient', LinearGradient)
})
```

The `.client.ts` suffix ensures this plugin only runs in the browser.

## Why no hydration mismatch?

The `<Shader>` component renders a `<canvas>` element. Canvas elements have no server-rendered HTML output - there's nothing for Vue to hydrate or compare. This means there's no hydration mismatch warning even if the canvas appears in the initial HTML. That said, the GPU initialization still requires a browser, so the `<ClientOnly>` wrapper (or mounted guard) is still needed to prevent SSR errors.
