---
title: BarShift
description: Slices content into parallel bars, each offset independently for a fractured or glitch-like effect
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# BarShift

Slices content into parallel bars, each offset independently for a fractured or glitch-like effect


::shader-preview{component="BarShift"}
::

## Props

::props-table
---
props:
  - name: "count"
    type: "number"
    default: "6"
    description: "Number of bars across the longest viewport dimension"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Angle of bar orientation in degrees (0 = vertical bars, 90 = horizontal bars)"
  - name: "intensity"
    type: "number"
    default: "0.15"
    description: "Maximum displacement per bar"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Randomization seed for per-bar offset variation"
  - name: "speed"
    type: "number"
    default: "0"
    description: "Animation speed - each bar drifts at its own rate and direction"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <BarShift
    :intensity="0.15"
  >
    <Circle />
  </BarShift>
</Shader>
```

```jsx
<Shader>
  <BarShift
    intensity={0.15}
  >
    <Circle />
  </BarShift>
</Shader>
```

```svelte
<Shader>
  <BarShift
    intensity={0.15}
  >
    <Circle />
  </BarShift>
</Shader>
```

```tsx
<Shader>
  <BarShift
    intensity={0.15}
  >
    <Circle />
  </BarShift>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'BarShift', props: { intensity: 0.15 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
