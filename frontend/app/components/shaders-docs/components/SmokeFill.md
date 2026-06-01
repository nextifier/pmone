---
title: SmokeFill
description: Fill a shape with swirling fluid smoke that interacts with the shape boundary
category: Shape Effects
componentType: Generator
requiresChild: false
---

# SmokeFill

Fill a shape with swirling fluid smoke that interacts with the shape boundary


::shader-preview{component="SmokeFill"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#8cf3ff"
    description: "Color of fresh smoke"
  - name: "colorB"
    type: "string"
    default: "#04a0d6"
    description: "Color smoke transitions to as it ages"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the shape"
  - name: "scale"
    type: "number"
    default: "1"
    description: "Scale of the shape (1 = default size)"
  - name: "emitFrom"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Emission source point within the shape"
  - name: "direction"
    type: "number"
    default: "0"
    description: "Emission direction (0 = up, 90 = right, 180 = down, 270 = left)"
  - name: "speed"
    type: "number"
    default: "10"
    description: "Emission velocity strength"
  - name: "spread"
    type: "number"
    default: "60"
    description: "Emission cone angle in degrees"
  - name: "emitRadius"
    type: "number"
    default: "0.03"
    description: "Size of the emission area"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Smoke emission density"
  - name: "dissipation"
    type: "number"
    default: "0.3"
    description: "How fast smoke fades over time"
  - name: "detail"
    type: "number"
    default: "25"
    description: "Fine-scale swirling detail"
  - name: "gravity"
    type: "number"
    default: "0.5"
    description: "Downward gravitational pull on smoke - 0 = weightless, negative values = smoke rises"
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
  - name: "shape"
    type: "ShapeConfig"
    default: "circleSDF"
    description: "Shape to render - choose from 11 built-in analytical shapes or supply a custom SDF. See the [Shape Effects guide](/docs/guide/shape-effects) for all available shapes and their options."
  - name: "shapeSdfUrl"
    type: "string"
    default: "\"\""
    description: "URL to a pre-generated SDF `.bin` file - when non-empty, activates SVG mode and triggers a shader recompile. See the [Shape Effects guide](/docs/guide/shape-effects) for how to generate an SDF from an SVG."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <SmokeFill
    :intensity="1"
  />
</Shader>
```

```jsx
<Shader>
  <SmokeFill
    intensity={1}
  />
</Shader>
```

```svelte
<Shader>
  <SmokeFill
    intensity={1}
  />
</Shader>
```

```tsx
<Shader>
  <SmokeFill
    intensity={1}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'SmokeFill', props: { intensity: 1 } }
  ]
})
```
::
