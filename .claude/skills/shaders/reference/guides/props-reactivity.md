---
title: Props & Reactivity
description: Control shader properties dynamically with reactive state and animations
icon: binary
category: features
---

# Props & Reactivity

Every shader component accepts props that control its appearance and behavior. These props are fully reactive-change a value in your component state, and the shader updates instantly.

## Basic Props

Props work exactly like standard component props. Pass them directly to configure your shaders:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Circle color="#ff0088" radius="0.5" />
</Shader>
```

```jsx
<Shader>
  <Circle color="#ff0088" radius={0.5} />
</Shader>
```

```tsx
<Shader>
  <Circle color="#ff0088" radius={0.5} />
</Shader>
```

```tsx
<Shader>
  <Circle color="#ff0088" radius={0.5} />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', props: { color: '#ff0088', radius: 0.5 } }
  ]
})
```
::

Static props like these are perfect for fixed effects. But the real power comes from reactive props.

## Reactive Props

Bind props to your component's state, and the shader updates automatically when the state changes. This happens efficiently-prop changes update GPU uniforms directly without recompiling shaders.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<script setup>
import { ref } from 'vue'
import { Shader, LinearGradient, Circle } from 'shaders/vue'

const angle = ref(0)
</script>

<template>
  <div>
    <button @click="angle += 45">Rotate +45°</button>
    <button @click="angle -= 45">Rotate -45°</button>
    <button @click="angle = 0">Reset</button>
  </div>

  <Shader>
    <Circle id="mask" radius="0.8" :visible="false" />
    <LinearGradient :angle="angle" maskSource="mask" />
  </Shader>
</template>
```

```jsx
import { useState } from 'react'
import { Shader, LinearGradient, Circle } from 'shaders/react'

function MyComponent() {
  const [angle, setAngle] = useState(0)

  return (
    <>
      <div>
        <button onClick={() => setAngle(a => a + 45)}>Rotate +45°</button>
        <button onClick={() => setAngle(a => a - 45)}>Rotate -45°</button>
        <button onClick={() => setAngle(0)}>Reset</button>
      </div>

      <Shader>
        <Circle id="mask" radius={0.8} visible={false} />
        <LinearGradient angle={angle} maskSource="mask" />
      </Shader>
    </>
  )
}
```

```tsx
<script>
  import { Shader, LinearGradient, Circle } from 'shaders/svelte'

  let angle = 0
</script>

<div>
  <button on:click={() => angle += 45}>Rotate +45°</button>
  <button on:click={() => angle -= 45}>Rotate -45°</button>
  <button on:click={() => angle = 0}>Reset</button>
</div>

<Shader>
  <Circle id="mask" radius={0.8} visible={false} />
  <LinearGradient {angle} maskSource="mask" />
</Shader>
```

```tsx
import { createSignal } from 'solid-js'
import { Shader, LinearGradient, Circle } from 'shaders/solid'

function MyComponent() {
  const [angle, setAngle] = createSignal(0)

  return (
    <>
      <div>
        <button onClick={() => setAngle(a => a + 45)}>Rotate +45°</button>
        <button onClick={() => setAngle(a => a - 45)}>Rotate -45°</button>
        <button onClick={() => setAngle(0)}>Reset</button>
      </div>

      <Shader>
        <Circle id="mask" radius={0.8} visible={false} />
        <LinearGradient angle={angle()} maskSource="mask" />
      </Shader>
    </>
  )
}
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', id: 'mask', props: { radius: 0.8, visible: false } },
    { type: 'LinearGradient', id: 'gradient', props: { angle: 0, maskSource: 'mask' } }
  ]
})

// Update angle at runtime
shader.update('gradient', { angle: 45 })

// Reset angle
shader.update('gradient', { angle: 0 })
```
::

Click the buttons, and the gradient rotates inside the circle instantly. No lag, no stutter-just smooth, real-time updates.

::interactive-shader-demo
---
preset:
  components:
    - type: Circle
      id: "mask"
      props:
        radius: 0.8
        visible: false
    - type: LinearGradient
      props:
        angle: 0
        maskSource: "mask"
controls:
  - type: button
    label: Rotate +45°
    action: increment
    prop: angle
    value: 45
  - type: button
    label: Rotate -45°
    action: increment
    prop: angle
    value: -45
  - type: button
    label: Reset
    action: set
    prop: angle
    value: 0
---
::

The angle updates immediately because props are reactive. Change any prop-color, position, intensity-and the GPU responds instantly.

## Common Reactive Props

Different components accept different props. Here are patterns you'll use frequently:

**Position and Size**:
```vue
<Circle :radius="size" :center="{ x: posX, y: posY }" />
```

**Colors**:
```vue
<LinearGradient :colorA="startColor" :colorB="endColor" />
```

**Intensity and Strength**:
```vue
<Blur :intensity="blurAmount" />
<Glow :strength="glowPower" />
```

**Angles and Rotation**:
```vue
<LinearGradient :angle="rotation" />
```

Every numerical prop, color prop, and position prop can be reactive. Bind them to sliders, scroll position, mouse coordinates, or any other dynamic value.

## Animating Props

Props aren't just for user interactions-they're perfect for animations too. Animation libraries like Motion make it easy to create smooth, choreographed transitions.

**Note**: Motion supports React and Vue, as well as plain JavaScript. You can use any animation library you like for Svelte/Solid.

```jsx
import { useState } from 'react'
import { animate } from 'motion'
import { Shader, LinearGradient, Circle } from 'shaders/react'

function MyComponent() {
  const [radius, setRadius] = useState(0.6)

  async function pulse() {
    // Grow then shrink with smooth easing
    await animate(radius, 1, {
      duration: 0.5,
      easing: 'ease-out',
      onUpdate: (latest) => setRadius(latest)
    })
    await animate(1, 0.6, {
      duration: 0.5,
      easing: 'ease-in',
      onUpdate: (latest) => setRadius(latest)
    })
  }

  return (
    <>
      <button onClick={pulse}>Pulse</button>

      <Shader>
        <Circle id="mask" radius={radius} visible={false} />
        <LinearGradient maskSource="mask" />
      </Shader>
    </>
  )
}
```

::interactive-shader-demo
---
preset:
  components:
    - type: Circle
      id: "mask"
      props:
        radius: 0.6
        visible: false
    - type: LinearGradient
      props:
        maskSource: "mask"
controls:
  - type: button
    label: Pulse
    action: sequence
    sequence:
      - prop: radius
        to: 1
        duration: 500
        easing: ease-out
      - prop: radius
        to: 0.6
        duration: 500
        easing: ease-in
---
::

Motion handles the tweening automatically, giving you smooth animations with minimal code. Because prop updates are efficient, you can animate multiple properties simultaneously without performance concerns.

## Performance Notes

Reactive props update GPU uniforms directly. This means:

- **No shader recompilation**: Changing a prop doesn't rebuild the shader
- **GPU-efficient**: Updates happen on the graphics card, not the CPU
- **Frame-rate friendly**: Animate as many props as you need

You can safely update props every frame, tie them to scroll position, or respond to mouse movement without worrying about performance.