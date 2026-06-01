---
title: Fog
description: Fog that fills the screen and interacts with the mouse
category: Interactive
componentType: Generator
requiresChild: false
---

# Fog

Fog that fills the screen and interacts with the mouse


::shader-preview{component="Fog"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#e0e0e0"
    description: "Primary fog color"
  - name: "colorB"
    type: "string"
    default: "#888888"
    description: "Secondary fog color - creates variation across the field"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Deterministic starting pattern - different seeds produce different fog configurations"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Simulation speed multiplier"
  - name: "turbulence"
    type: "number"
    default: "1"
    description: "Ambient motion strength"
  - name: "detail"
    type: "number"
    default: "15"
    description: "Fine-scale swirling structure - higher values produce more intricate wisps and vortices"
  - name: "blending"
    type: "number"
    default: "0.3"
    description: "How much the two colors blend together - 0 behaves like oil & water (colors stay distinct with sharp boundaries), 1 behaves like food coloring (colors fully mix)"
  - name: "mouseInfluence"
    type: "number"
    default: "0.1"
    description: "Strength of cursor influence - move the cursor to push fog"
  - name: "mouseRadius"
    type: "number"
    default: "0.1"
    description: "Radius of cursor influence area"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Fog />
</Shader>
```

```jsx
<Shader>
  <Fog />
</Shader>
```

```svelte
<Shader>
  <Fog />
</Shader>
```

```tsx
<Shader>
  <Fog />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Fog', props: {} }
  ]
})
```
::
