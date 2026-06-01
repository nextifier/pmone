---
title: CursorRipples
description: Fluid-like ripple distortion
category: Interactive
componentType: Filter/Effect
requiresChild: true
---

# CursorRipples

Fluid-like ripple distortion


::shader-preview{component="CursorRipples"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "10"
    description: "Strength of the ripple distortion"
  - name: "decay"
    type: "number"
    default: "10"
    description: "How quickly ripples fade (higher = faster)"
  - name: "radius"
    type: "number"
    default: "0.5"
    description: "Radius of cursor influence"
  - name: "chromaticSplit"
    type: "number"
    default: "1"
    description: "RGB channel separation along ripple edges"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <CursorRipples
    :intensity="10"
    :radius="0.5"
  >
    <Circle />
  </CursorRipples>
</Shader>
```

```jsx
<Shader>
  <CursorRipples
    intensity={10}
    radius={0.5}
  >
    <Circle />
  </CursorRipples>
</Shader>
```

```svelte
<Shader>
  <CursorRipples
    intensity={10}
    radius={0.5}
  >
    <Circle />
  </CursorRipples>
</Shader>
```

```tsx
<Shader>
  <CursorRipples
    intensity={10}
    radius={0.5}
  >
    <Circle />
  </CursorRipples>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'CursorRipples', props: { intensity: 10, radius: 0.5 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
