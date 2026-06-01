---
title: Truchet
description: Quarter-circle arc tiles that connect to form organic, maze-like flowing curves
category: Textures
componentType: Generator
requiresChild: false
---

# Truchet

Quarter-circle arc tiles that connect to form organic, maze-like flowing curves


::shader-preview{component="Truchet"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "Background color between the arcs"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Arc line color"
  - name: "cells"
    type: "number"
    default: "10"
    description: "Number of tiles across the shortest canvas edge"
  - name: "thickness"
    type: "number"
    default: "2"
    description: "Thickness of the arc lines"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed - changes which tiles flip, producing a different maze pattern"
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
  <Truchet />
</Shader>
```

```jsx
<Shader>
  <Truchet />
</Shader>
```

```svelte
<Shader>
  <Truchet />
</Shader>
```

```tsx
<Shader>
  <Truchet />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Truchet', props: {} }
  ]
})
```
::
