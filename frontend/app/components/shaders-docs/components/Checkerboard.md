---
title: Checkerboard
description: Classic checkerboard pattern with two alternating colors
category: Textures
componentType: Generator
requiresChild: false
---

# Checkerboard

Classic checkerboard pattern with two alternating colors


::shader-preview{component="Checkerboard"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#cccccc"
    description: "First color of the checkerboard pattern"
  - name: "colorB"
    type: "string"
    default: "#999999"
    description: "Second color of the checkerboard pattern"
  - name: "cells"
    type: "number"
    default: "8"
    description: "Number of cells along the shortest canvas edge (creates square cells)"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Smoothness of the transition between colors (0 = hard edges, 1 = very soft)"
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
  <Checkerboard />
</Shader>
```

```jsx
<Shader>
  <Checkerboard />
</Shader>
```

```svelte
<Shader>
  <Checkerboard />
</Shader>
```

```tsx
<Shader>
  <Checkerboard />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Checkerboard', props: {} }
  ]
})
```
::
