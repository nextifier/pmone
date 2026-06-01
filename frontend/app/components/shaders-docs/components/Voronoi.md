---
title: Voronoi
description: Cellular pattern where each pixel is colored by its distance to the nearest of many scattered points
category: Textures
componentType: Generator
requiresChild: false
---

# Voronoi

Cellular pattern where each pixel is colored by its distance to the nearest of many scattered points


::shader-preview{component="Voronoi"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#3186cf"
    description: "Color near each cell's center point"
  - name: "colorB"
    type: "string"
    default: "#fc02dd"
    description: "Color at cell boundaries, far from any center point"
  - name: "colorBorder"
    type: "string"
    default: "#000000"
    description: "Color of the cell boundary lines"
  - name: "scale"
    type: "number"
    default: "6"
    description: "Number of cells across the canvas"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Animation speed - how fast the cell points drift"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed - shifts the cell pattern without changing the overall structure"
  - name: "edgeIntensity"
    type: "number"
    default: "0.5"
    description: "Controls how much of the cell interior is filled by the edge color. Low = center color dominates with a sharp boundary. High = edge color spreads further into the cell."
  - name: "edgeSoftness"
    type: "number"
    default: "0.05"
    description: "Width of the cell boundary lines."
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "oklch"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Voronoi />
</Shader>
```

```jsx
<Shader>
  <Voronoi />
</Shader>
```

```svelte
<Shader>
  <Voronoi />
</Shader>
```

```tsx
<Shader>
  <Voronoi />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Voronoi', props: {} }
  ]
})
```
::
