---
title: Hooks & Events
description: Respond to shader lifecycle events like GPU compilation and readiness
icon: bell
category: advanced
---

# Hooks & Events

Shaders provides lifecycle hooks that let you respond to key moments in the rendering pipeline. This is useful for orchestrating UI transitions, hiding loading states, or triggering animations once the shader is ready.

## onReady

The `onReady` hook fires once after the GPU has compiled the shader and the first frame is ready to render. This is the ideal moment to fade in the shader, remove a loading skeleton, or start a dependent animation.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<template>
  <Shader @ready="onShaderReady" :style="{ opacity: visible ? 1 : 0, transition: 'opacity 0.5s' }">
    <Circle color="#ff0088" />
  </Shader>
</template>

<script setup>
import { ref } from 'vue'

const visible = ref(false)

function onShaderReady() {
  visible.value = true
}
</script>
```

```jsx
import { useState } from 'react'

function App() {
  const [visible, setVisible] = useState(false)

  return (
    <Shader
      onReady={() => setVisible(true)}
      style={{ opacity: visible ? 1 : 0, transition: 'opacity 0.5s' }}
    >
      <Circle color="#ff0088" />
    </Shader>
  )
}
```

```svelte
<script>
  let visible = $state(false)
</script>

<Shader onready={() => visible = true} style:opacity={visible ? 1 : 0} style:transition="opacity 0.5s">
  <Circle color="#ff0088" />
</Shader>
```

```tsx
import { createSignal } from 'solid-js'

function App() {
  const [visible, setVisible] = createSignal(false)

  return (
    <Shader
      onReady={() => setVisible(true)}
      style={{ opacity: visible() ? 1 : 0, transition: 'opacity 0.5s' }}
    >
      <Circle color="#ff0088" />
    </Shader>
  )
}
```

```js
const canvas = document.getElementById('my-canvas')

// Start hidden
canvas.style.opacity = '0'
canvas.style.transition = 'opacity 0.5s'

const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', id: 'c1', props: { color: '#ff0088' } }
  ]
}, {
  onReady: () => {
    canvas.style.opacity = '1'
  }
})
```
::

### Timing

`onReady` fires after the shader's node tree has been compiled into a GPU material and is actively rendering. This means:

- The WebGPU (or WebGL fallback) renderer is initialized
- All child shader components have registered
- The composed shader material has been compiled
- The first frame is ready to display

The callback fires **once** per shader lifecycle. If the component is unmounted and remounted, it will fire again after recompilation.

### Notes

- `onReady` is called asynchronously (via microtask) so the rendered frame is available by the time your callback executes
- If the `<Shader>` component starts off-screen and initializes lazily when scrolled into view, `onReady` fires after that deferred initialization completes
