---
title: FractalNoise
description: Multi-octave fractal Brownian motion noise texture with true noise evolution
category: Textures
componentType: Generator
requiresChild: false
---

# FractalNoise

Multi-octave fractal Brownian motion noise texture with true noise evolution


::shader-preview{component="FractalNoise"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "First color"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Second color"
  - name: "octaves"
    type: "number"
    default: "4"
    description: "Number of noise octaves (more = more detail)"
  - name: "detail"
    type: "number"
    default: "2"
    description: "How much finer each successive octave becomes"
  - name: "contrast"
    type: "number"
    default: "0.5"
    description: "How strongly finer octaves contribute - higher values create more texture contrast"
  - name: "speed"
    type: "number"
    default: "0.15"
    description: "Speed at which the noise pattern evolves in place"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Rotation angle in degrees"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for pattern variation"
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
  <FractalNoise />
</Shader>
```

```jsx
<Shader>
  <FractalNoise />
</Shader>
```

```svelte
<Shader>
  <FractalNoise />
</Shader>
```

```tsx
<Shader>
  <FractalNoise />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FractalNoise', props: {} }
  ]
})
```
::
