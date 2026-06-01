---
title: HexGrid
description: Honeycomb hexagonal grid pattern
category: Textures
componentType: Generator
requiresChild: false
---

# HexGrid

Honeycomb hexagonal grid pattern


::shader-preview{component="HexGrid"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "Cell fill color"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Grid line color"
  - name: "cells"
    type: "number"
    default: "8"
    description: "Number of hexagons across the shortest canvas edge"
  - name: "thickness"
    type: "number"
    default: "1"
    description: "Thickness of the hex grid lines"
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
  <HexGrid />
</Shader>
```

```jsx
<Shader>
  <HexGrid />
</Shader>
```

```svelte
<Shader>
  <HexGrid />
</Shader>
```

```tsx
<Shader>
  <HexGrid />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'HexGrid', props: {} }
  ]
})
```
::
