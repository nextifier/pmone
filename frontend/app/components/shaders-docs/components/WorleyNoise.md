---
title: WorleyNoise
description: Cellular noise field - distance-based, with selectable feature combinations and fractal octaves
category: Textures
componentType: Generator
requiresChild: false
---

# WorleyNoise

Cellular noise field - distance-based, with selectable feature combinations and fractal octaves


::shader-preview{component="WorleyNoise"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ffffff"
    description: "Color where the noise field is low (typically near cell centers)"
  - name: "colorB"
    type: "string"
    default: "#000000"
    description: "Color where the noise field is high (typically near cell boundaries)"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
  - name: "scale"
    type: "number"
    default: "6"
    description: "Number of cells across the canvas at the base octave"
  - name: "mode"
    type: "\"f1\" | \"f2\" | \"f2MinusF1\" | \"f1PlusF2\" | \"f1TimesF2\""
    default: "f1"
    description: "Field type. F1 = distance to nearest point. F2 = distance to second-nearest. F2 − F1 emphasises cell boundaries."
  - name: "distance"
    type: "\"euclidean\" | \"manhattan\" | \"chebyshev\""
    default: "euclidean"
    description: "Distance metric. Euclidean = round cells. Manhattan = diamond. Chebyshev = square."
  - name: "octaves"
    type: "number"
    default: "1"
    description: "Number of fractal layers stacked at progressively finer scales"
  - name: "lacunarity"
    type: "number"
    default: "2"
    description: "Scale multiplier between octaves (only active when Octaves > 1)"
  - name: "persistence"
    type: "number"
    default: "0.5"
    description: "Amplitude multiplier between octaves (only active when Octaves > 1)"
  - name: "jitter"
    type: "number"
    default: "1"
    description: "How much each cell's point drifts inside its cell. 0 = rigid grid (banded look), 1 = fully random."
  - name: "contrast"
    type: "number"
    default: "1"
    description: "Steepness of the gradient between low and high regions"
  - name: "balance"
    type: "number"
    default: "0"
    description: "Shifts the gradient midpoint. Negative pulls the field toward Color A, positive toward Color B."
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed - shifts the cell pattern without changing its overall structure"
  - name: "speed"
    type: "number"
    default: "0.5"
    description: "Animation speed - how fast each cell's point drifts"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <WorleyNoise />
</Shader>
```

```jsx
<Shader>
  <WorleyNoise />
</Shader>
```

```svelte
<Shader>
  <WorleyNoise />
</Shader>
```

```tsx
<Shader>
  <WorleyNoise />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'WorleyNoise', props: {} }
  ]
})
```
::
