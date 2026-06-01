---
title: TiltShift
description: Selective focus blur mimicking tilt-shift photography
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# TiltShift

Selective focus blur mimicking tilt-shift photography


::shader-preview{component="TiltShift"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "50"
    description: "Maximum blur intensity at edges"
  - name: "width"
    type: "number"
    default: "0.3"
    description: "Width of the sharp focus area"
  - name: "falloff"
    type: "number"
    default: "0.3"
    description: "Distance over which blur transitions to full strength"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Rotation angle of the focus line (in degrees)"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the focus line"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <TiltShift
    :intensity="50"
  >
    <Circle />
  </TiltShift>
</Shader>
```

```jsx
<Shader>
  <TiltShift
    intensity={50}
  >
    <Circle />
  </TiltShift>
</Shader>
```

```svelte
<Shader>
  <TiltShift
    intensity={50}
  >
    <Circle />
  </TiltShift>
</Shader>
```

```tsx
<Shader>
  <TiltShift
    intensity={50}
  >
    <Circle />
  </TiltShift>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'TiltShift', props: { intensity: 50 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
