---
title: Perspective
description: Rotate the plane in 3D space with pan and tilt
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Perspective

Rotate the plane in 3D space with pan and tilt


::shader-preview{component="Perspective"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of rotation"
  - name: "pan"
    type: "number"
    default: "0"
    description: "Horizontal rotation (left/right)"
  - name: "tilt"
    type: "number"
    default: "0"
    description: "Vertical rotation (up/down)"
  - name: "fov"
    type: "number"
    default: "60"
    description: "Field of view - controls perspective intensity"
  - name: "zoom"
    type: "number"
    default: "1"
    description: "Zoom in to fill the frame after rotation"
  - name: "offset"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Shift the result in X/Y"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "transparent"
    description: "How to handle edges"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Perspective>
    <Circle />
  </Perspective>
</Shader>
```

```jsx
<Shader>
  <Perspective>
    <Circle />
  </Perspective>
</Shader>
```

```svelte
<Shader>
  <Perspective>
    <Circle />
  </Perspective>
</Shader>
```

```tsx
<Shader>
  <Perspective>
    <Circle />
  </Perspective>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Perspective', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
