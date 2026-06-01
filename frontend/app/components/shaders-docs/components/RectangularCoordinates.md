---
title: RectangularCoordinates
description: Convert polar coordinates back to rectangular space
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# RectangularCoordinates

Convert polar coordinates back to rectangular space


::shader-preview{component="RectangularCoordinates"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point for rectangular coordinate conversion"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale factor for the rectangular output"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Blends between original UVs (0) and rectangular coordinates (1)"
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
  <RectangularCoordinates
    :intensity="1"
  >
    <Circle />
  </RectangularCoordinates>
</Shader>
```

```jsx
<Shader>
  <RectangularCoordinates
    intensity={1}
  >
    <Circle />
  </RectangularCoordinates>
</Shader>
```

```svelte
<Shader>
  <RectangularCoordinates
    intensity={1}
  >
    <Circle />
  </RectangularCoordinates>
</Shader>
```

```tsx
<Shader>
  <RectangularCoordinates
    intensity={1}
  >
    <Circle />
  </RectangularCoordinates>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'RectangularCoordinates', props: { intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
