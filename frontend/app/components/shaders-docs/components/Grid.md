---
title: Grid
description: Simple grid lines pattern with adjustable thickness and rotation
category: Textures
componentType: Generator
requiresChild: false
---

# Grid

Simple grid lines pattern with adjustable thickness and rotation


::shader-preview{component="Grid"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "The color of the grid lines"
  - name: "cells"
    type: "number"
    default: "10"
    description: "Number of cells along the shortest canvas edge (creates square cells)"
  - name: "thickness"
    type: "number"
    default: "1"
    description: "Thickness of grid lines (normalized, 0.0-1.0)"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation of the grid in degrees. At 45° this produces a crosshatch/diamond pattern."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Grid
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <Grid
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <Grid
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <Grid
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Grid', props: { color: '#ffffff' } }
  ]
})
```
::
