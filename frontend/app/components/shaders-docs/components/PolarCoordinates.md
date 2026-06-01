---
title: PolarCoordinates
description: Convert rectangular coordinates to polar space
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# PolarCoordinates

Convert rectangular coordinates to polar space


::shader-preview{component="PolarCoordinates"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point for polar coordinate conversion"
  - name: "wrap"
    type: "number"
    default: "1"
    description: "Controls how much of the angular range to use (1 = full 360°, 0.5 = 180°)"
  - name: "radius"
    type: "number"
    default: "1"
    description: "Controls how much of the radius range to use (affects the radial mapping)"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Blends between original UVs (0) and polar coordinates (1)"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "transparent"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <PolarCoordinates
    :radius="1"
    :intensity="1"
  >
    <Circle />
  </PolarCoordinates>
</Shader>
```

```jsx
<Shader>
  <PolarCoordinates
    radius={1}
    intensity={1}
  >
    <Circle />
  </PolarCoordinates>
</Shader>
```

```svelte
<Shader>
  <PolarCoordinates
    radius={1}
    intensity={1}
  >
    <Circle />
  </PolarCoordinates>
</Shader>
```

```tsx
<Shader>
  <PolarCoordinates
    radius={1}
    intensity={1}
  >
    <Circle />
  </PolarCoordinates>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'PolarCoordinates', props: { radius: 1, intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
