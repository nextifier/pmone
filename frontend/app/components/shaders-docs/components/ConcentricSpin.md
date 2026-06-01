---
title: ConcentricSpin
description: Concentric rings that each rotate the underlying image by different amounts
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# ConcentricSpin

Concentric rings that each rotate the underlying image by different amounts


::shader-preview{component="ConcentricSpin"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "20"
    description: "Maximum rotation angle per ring"
  - name: "rings"
    type: "number"
    default: "8"
    description: "Number of concentric rings"
  - name: "smoothness"
    type: "number"
    default: "0.03"
    description: "Softness of transitions between rings"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Randomization seed for per-ring rotation variation"
  - name: "speed"
    type: "number"
    default: "0.1"
    description: "Speed of continuous ring rotation"
  - name: "speedRandomness"
    type: "number"
    default: "0.5"
    description: "How much each ring varies in rotation speed and direction"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when distortion pushes content out of bounds"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the concentric rings"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ConcentricSpin
    :intensity="20"
  >
    <Circle />
  </ConcentricSpin>
</Shader>
```

```jsx
<Shader>
  <ConcentricSpin
    intensity={20}
  >
    <Circle />
  </ConcentricSpin>
</Shader>
```

```svelte
<Shader>
  <ConcentricSpin
    intensity={20}
  >
    <Circle />
  </ConcentricSpin>
</Shader>
```

```tsx
<Shader>
  <ConcentricSpin
    intensity={20}
  >
    <Circle />
  </ConcentricSpin>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ConcentricSpin', props: { intensity: 20 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
