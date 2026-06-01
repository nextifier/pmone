---
title: Smoke
description: Realistic fluid smoke simulation with vorticity dynamics
category: Interactive
componentType: Generator
requiresChild: false
---

# Smoke

Realistic fluid smoke simulation with vorticity dynamics


::shader-preview{component="Smoke"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#fc83f9"
    description: "Color of fresh smoke"
  - name: "colorB"
    type: "string"
    default: "#c21c79"
    description: "Color smoke transitions to as it ages"
  - name: "emitFrom"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":1}"
    description: "The emission source point"
  - name: "direction"
    type: "number"
    default: "0"
    description: "Emission direction (0 = up, 90 = right, 180 = down, 270 = left)"
  - name: "speed"
    type: "number"
    default: "20"
    description: "Emission velocity strength"
  - name: "spread"
    type: "number"
    default: "60"
    description: "Emission cone angle in degrees"
  - name: "emitRadius"
    type: "number"
    default: "0.08"
    description: "Size of the emission area"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Smoke emission density"
  - name: "dissipation"
    type: "number"
    default: "0.2"
    description: "How fast smoke fades over time"
  - name: "detail"
    type: "number"
    default: "25"
    description: "Fine-scale swirling detail"
  - name: "gravity"
    type: "number"
    default: "0.5"
    description: "Downward gravitational pull on smoke"
  - name: "colorDecay"
    type: "number"
    default: "0.4"
    description: "How quickly smoke shifts from Color A to Color B"
  - name: "mouseInfluence"
    type: "number"
    default: "0.1"
    description: "Strength of cursor influence"
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
  <Smoke
    :intensity="1"
  />
</Shader>
```

```jsx
<Shader>
  <Smoke
    intensity={1}
  />
</Shader>
```

```svelte
<Shader>
  <Smoke
    intensity={1}
  />
</Shader>
```

```tsx
<Shader>
  <Smoke
    intensity={1}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Smoke', props: { intensity: 1 } }
  ]
})
```
::
