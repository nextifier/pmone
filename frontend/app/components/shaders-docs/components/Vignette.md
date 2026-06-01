---
title: Vignette
description: Darkens or tints the edges of the frame, drawing attention toward the center
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Vignette

Darkens or tints the edges of the frame, drawing attention toward the center


::shader-preview{component="Vignette"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#000000"
    description: "Color of the vignette at the edges"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center of the clear area where the vignette begins"
  - name: "radius"
    type: "number"
    default: "0.5"
    description: "Distance from center where the vignette begins to fade in"
  - name: "falloff"
    type: "number"
    default: "0.5"
    description: "Width of the transition zone from clear to full vignette"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Strength of the vignette effect"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Vignette
    color="#000000"
    :radius="0.5"
    :intensity="1"
  >
    <Circle />
  </Vignette>
</Shader>
```

```jsx
<Shader>
  <Vignette
    color="#000000"
    radius={0.5}
    intensity={1}
  >
    <Circle />
  </Vignette>
</Shader>
```

```svelte
<Shader>
  <Vignette
    color="#000000"
    radius={0.5}
    intensity={1}
  >
    <Circle />
  </Vignette>
</Shader>
```

```tsx
<Shader>
  <Vignette
    color="#000000"
    radius={0.5}
    intensity={1}
  >
    <Circle />
  </Vignette>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Vignette', props: { color: '#000000', radius: 0.5, intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
