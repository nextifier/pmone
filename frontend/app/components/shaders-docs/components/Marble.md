---
title: Marble
description: Classic marble swirl and vein texture using noise-warped sine waves
category: Textures
componentType: Generator
requiresChild: false
---

# Marble

Classic marble swirl and vein texture using noise-warped sine waves


::shader-preview{component="Marble"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ffffff"
    description: "Base background color of the marble"
  - name: "colorB"
    type: "string"
    default: "#3a2d54"
    description: "Secondary marble tone"
  - name: "colorC"
    type: "string"
    default: "#0f0f0f"
    description: "Deepest marble color"
  - name: "scale"
    type: "number"
    default: "2"
    description: "Scale and density of the marble vein pattern"
  - name: "turbulence"
    type: "number"
    default: "10"
    description: "Amount of noise-driven distortion applied to the veins"
  - name: "speed"
    type: "number"
    default: "0.05"
    description: "Animation speed"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for pattern variation"
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
  <Marble />
</Shader>
```

```jsx
<Shader>
  <Marble />
</Shader>
```

```svelte
<Shader>
  <Marble />
</Shader>
```

```tsx
<Shader>
  <Marble />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Marble', props: {} }
  ]
})
```
::
