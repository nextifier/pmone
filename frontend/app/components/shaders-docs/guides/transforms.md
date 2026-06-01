---
title: Transforms
description: Reposition, rotate, and scale any shader component in UV space
icon: arrows-to-circle
category: features
---

# Transforms

Every component accepts a `transform` prop that moves, rotates, and scales it in UV space - the coordinate system the shader uses to sample its output.

This is different from CSS transforms. A CSS transform moves the canvas element in the DOM layout. A shader transform shifts how the shader *samples its coordinate system*, so the effect itself shifts without touching the DOM.

```vue-html
<!-- CSS transform: moves the DOM element -->
<Shader class="-translate-x-4">...</Shader>

<!-- Shader transform: shifts content within the canvas -->
<Shader>
  <LinearGradient :transform="{ offsetX: -0.2 }" />
</Shader>
```

## Transform properties

Pass a partial object to the `transform` prop - only include what you want to change:

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `offsetX` | `number` | `0` | Horizontal shift. `-1` to `+1`. `-1` shifts content fully left, `+1` fully right. |
| `offsetY` | `number` | `0` | Vertical shift. `-1` to `+1`. |
| `rotation` | `number` | `0` | Rotation in degrees. Positive = clockwise. |
| `scale` | `number` | `1` | Scale multiplier. `0.5` = half size, `2` = double. |
| `anchorX` | `number` | `0.5` | Horizontal pivot point for rotation and scale. `0` = left, `1` = right. |
| `anchorY` | `number` | `0.5` | Vertical pivot point. `0` = top, `1` = bottom. |
| `edges` | `string` | `'transparent'` | What to show when the transform pushes content out of bounds. See below. |

## Basic usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader class="w-full h-64">
  <!-- Rotate 45 degrees -->
  <LinearGradient :transform="{ rotation: 45 }" />

  <!-- Scale up and shift right -->
  <Circle :transform="{ scale: 1.5, offsetX: 0.2 }" />

  <!-- Multiple transforms together -->
  <Swirl :transform="{ rotation: 30, scale: 0.8, offsetY: -0.1 }" />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient transform={{ rotation: 45 }} />
  <Circle transform={{ scale: 1.5, offsetX: 0.2 }} />
  <Swirl transform={{ rotation: 30, scale: 0.8, offsetY: -0.1 }} />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient transform={{ rotation: 45 }} />
  <Circle transform={{ scale: 1.5, offsetX: 0.2 }} />
  <Swirl transform={{ rotation: 30, scale: 0.8, offsetY: -0.1 }} />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient transform={{ rotation: 45 }} />
  <Circle transform={{ scale: 1.5, offsetX: 0.2 }} />
  <Swirl transform={{ rotation: 30, scale: 0.8, offsetY: -0.1 }} />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    {
      type: 'LinearGradient',
      props: {
        transform: { rotation: 45 }
      }
    },
    {
      type: 'Circle',
      props: {
        transform: { scale: 1.5, offsetX: 0.2 }
      }
    }
  ]
})
```
::

You only need to include the properties you want to change - unspecified values stay at their defaults.

## Anchor point

The anchor point controls where rotation and scale are applied from. The default `0.5, 0.5` anchors to the center of the canvas.

- `{ anchorX: 0, anchorY: 0 }` - top-left corner
- `{ anchorX: 0.5, anchorY: 0.5 }` - center (default)
- `{ anchorX: 1, anchorY: 1 }` - bottom-right corner

```vue-html
<!-- Rotates around the center (default) -->
<Circle :transform="{ rotation: 45 }" />

<!-- Rotates around the top-left corner -->
<Circle :transform="{ rotation: 45, anchorX: 0, anchorY: 0 }" />

<!-- Scales from the top edge -->
<LinearGradient :transform="{ scale: 2, anchorY: 0 }" />
```

## Edge modes

When a transform shifts or scales content beyond the canvas boundaries, the `edges` property controls what fills the out-of-bounds area:

| Value | Behavior |
|-------|----------|
| `transparent` | Out-of-bounds pixels are fully transparent (default) |
| `stretch` | The edge pixels repeat to fill |
| `mirror` | Content reflects at the edges like a mirror |
| `wrap` | Content tiles seamlessly |

```vue-html
<Shader class="w-full h-64">
  <!-- Offset with wrapping - tiles the effect -->
  <LinearGradient :transform="{ offsetX: 0.3, edges: 'wrap' }" />

  <!-- Offset with mirror - reflects the edge -->
  <Swirl :transform="{ offsetX: 0.3, edges: 'mirror' }" />
</Shader>
```

## Animating transforms

Transforms are reactive props - you can bind them to state and animate them:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const rotation = ref(0)
let animFrame

onMounted(() => {
  const animate = () => {
    rotation.value = (rotation.value + 0.5) % 360
    animFrame = requestAnimationFrame(animate)
  }
  animFrame = requestAnimationFrame(animate)
})

onUnmounted(() => cancelAnimationFrame(animFrame))
</script>

<template>
  <Shader class="w-full h-64">
    <LinearGradient :transform="{ rotation }" />
  </Shader>
</template>
```

```jsx
import { useState, useEffect, useRef } from 'react'

function SpinningGradient() {
  const [rotation, setRotation] = useState(0)
  const frameRef = useRef(null)

  useEffect(() => {
    const animate = () => {
      setRotation(r => (r + 0.5) % 360)
      frameRef.current = requestAnimationFrame(animate)
    }
    frameRef.current = requestAnimationFrame(animate)
    return () => cancelAnimationFrame(frameRef.current)
  }, [])

  return (
    <Shader className="w-full h-64">
      <LinearGradient transform={{ rotation }} />
    </Shader>
  )
}
```

```tsx
<script>
  import { onMount, onDestroy } from 'svelte'

  let rotation = 0
  let animFrame

  onMount(() => {
    const animate = () => {
      rotation = (rotation + 0.5) % 360
      animFrame = requestAnimationFrame(animate)
    }
    animFrame = requestAnimationFrame(animate)
  })

  onDestroy(() => cancelAnimationFrame(animFrame))
</script>

<Shader class="w-full h-64">
  <LinearGradient transform={{ rotation }} />
</Shader>
```

```tsx
import { createSignal, onMount, onCleanup } from 'solid-js'

function SpinningGradient() {
  const [rotation, setRotation] = createSignal(0)

  onMount(() => {
    let frame
    const animate = () => {
      setRotation(r => (r + 0.5) % 360)
      frame = requestAnimationFrame(animate)
    }
    frame = requestAnimationFrame(animate)
    onCleanup(() => cancelAnimationFrame(frame))
  })

  return (
    <Shader class="w-full h-64">
      <LinearGradient transform={{ rotation: rotation() }} />
    </Shader>
  )
}
```

```javascript
import { createShader } from 'shaders/js'

const canvas = document.getElementById('my-shader')
const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', id: 'grad', props: { colorA: '#0f172a', colorB: '#7c3aed' } }
  ]
})

let rotation = 0
const animate = () => {
  rotation = (rotation + 0.5) % 360
  shader.update('grad', { transform: { rotation } })
  requestAnimationFrame(animate)
}
requestAnimationFrame(animate)
```
::

For continuous animations like rotation, consider using a [Prop Driver](/docs/guide/dynamic-props) instead - the `auto-animate` driver handles this declaratively without any component code.

## Performance

Transforms have **zero overhead when all values are at their defaults**. The renderer checks for non-default values before activating the UV transformation pass.

Once a non-default transform is applied, a render-to-texture (RTT) pass activates for that component. This RTT persists for the lifetime of the component - even if values return to defaults - to prevent a recompilation flash during animations.

The practical implication: if you're conditionally showing/hiding a transform during an animation, the small RTT cost is paid once and stays, rather than toggling on and off.
