---
title: ProgressiveBlur
description: Blur that increases progressively in one direction
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# ProgressiveBlur

Blur that increases progressively in one direction


::shader-preview{component="ProgressiveBlur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "50"
    description: "Maximum intensity of the blur effect"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction of the blur gradient (in degrees)"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0,\"y\":0.5}"
    description: "Center point where blur begins"
  - name: "falloff"
    type: "number"
    default: "1"
    description: "Distance over which blur transitions to full strength"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ProgressiveBlur
    :intensity="50"
  >
    <Circle />
  </ProgressiveBlur>
</Shader>
```

```jsx
<Shader>
  <ProgressiveBlur
    intensity={50}
  >
    <Circle />
  </ProgressiveBlur>
</Shader>
```

```svelte
<Shader>
  <ProgressiveBlur
    intensity={50}
  >
    <Circle />
  </ProgressiveBlur>
</Shader>
```

```tsx
<Shader>
  <ProgressiveBlur
    intensity={50}
  >
    <Circle />
  </ProgressiveBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ProgressiveBlur', props: { intensity: 50 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
