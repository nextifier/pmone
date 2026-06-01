---
title: Halftone
description: Halftone dot pattern effect for printing aesthetics
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Halftone

Halftone dot pattern effect for printing aesthetics


::shader-preview{component="Halftone"}
::

## Props

::props-table
---
props:
  - name: "style"
    type: "\"classic\" | \"cmyk\""
    default: "classic"
    description: "Halftone rendering style"
  - name: "frequency"
    type: "number"
    default: "100"
    description: "Frequency of the halftone dots"
  - name: "angle"
    type: "number"
    default: "45"
    description: "Rotation angle of the pattern (in degrees)"
  - name: "cyanAngle"
    type: "number"
    default: "15"
    description: "Screen angle for the cyan plate (in degrees)"
  - name: "magentaAngle"
    type: "number"
    default: "75"
    description: "Screen angle for the magenta plate (in degrees)"
  - name: "yellowAngle"
    type: "number"
    default: "0"
    description: "Screen angle for the yellow plate (in degrees)"
  - name: "blackAngle"
    type: "number"
    default: "45"
    description: "Screen angle for the black plate (in degrees)"
  - name: "misprint"
    type: "number"
    default: "0"
    description: "Simulated mis-registration between plates. Plates are offset around the misprint angle, producing colour fringing at the edges of inked regions."
  - name: "misprintAngle"
    type: "number"
    default: "0"
    description: "Direction the plates drift apart. Rotating this rotates the colour-fringing pattern."
  - name: "paperColor"
    type: "string"
    default: "#ffffff"
    description: "Paper/substrate color shown where no ink lands"
  - name: "cyanColor"
    type: "string"
    default: "#00ffff"
    description: "Cyan ink color"
  - name: "magentaColor"
    type: "string"
    default: "#ff00ff"
    description: "Magenta ink color"
  - name: "yellowColor"
    type: "string"
    default: "#ffff00"
    description: "Yellow ink color"
  - name: "blackColor"
    type: "string"
    default: "#000000"
    description: "Black (key) ink color"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Halftone>
    <Circle />
  </Halftone>
</Shader>
```

```jsx
<Shader>
  <Halftone>
    <Circle />
  </Halftone>
</Shader>
```

```svelte
<Shader>
  <Halftone>
    <Circle />
  </Halftone>
</Shader>
```

```tsx
<Shader>
  <Halftone>
    <Circle />
  </Halftone>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Halftone', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
