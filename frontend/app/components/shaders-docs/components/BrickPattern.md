---
title: BrickPattern
description: Classic brick wall pattern with alternating rows and mortar gaps
category: Textures
componentType: Generator
requiresChild: false
---

# BrickPattern

Classic brick wall pattern with alternating rows and mortar gaps


::shader-preview{component="BrickPattern"}
::

## Props

::props-table
---
props:
  - name: "colorBrick"
    type: "string"
    default: "#000000"
    description: "Brick color"
  - name: "colorMortar"
    type: "string"
    default: "#ffffff"
    description: "Mortar / gap color"
  - name: "cellsX"
    type: "number"
    default: "8"
    description: "Number of bricks per row"
  - name: "cellsY"
    type: "number"
    default: "10"
    description: "Number of brick rows"
  - name: "mortar"
    type: "number"
    default: "0.05"
    description: "Width of mortar gaps - equal pixel thickness in both directions"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Rotation angle in degrees"
  - name: "speed"
    type: "number"
    default: "0"
    description: "Animation speed"
  - name: "offset"
    type: "number"
    default: "0"
    description: "Static horizontal offset - shifts the brick pattern without animating"
  - name: "speedVariance"
    type: "number"
    default: "0"
    description: "How much each row's speed varies - at high values rows move at different speeds and directions"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for per-row speed variation"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <BrickPattern />
</Shader>
```

```jsx
<Shader>
  <BrickPattern />
</Shader>
```

```svelte
<Shader>
  <BrickPattern />
</Shader>
```

```tsx
<Shader>
  <BrickPattern />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'BrickPattern', props: {} }
  ]
})
```
::
