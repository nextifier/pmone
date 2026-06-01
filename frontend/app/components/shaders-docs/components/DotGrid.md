---
title: DotGrid
description: Grid of dots with optional twinkling animation
category: Textures
componentType: Generator
requiresChild: false
---

# DotGrid

Grid of dots with optional twinkling animation


::shader-preview{component="DotGrid"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "The color of the dot"
  - name: "density"
    type: "number"
    default: "30"
    description: "The number of dots on the longest canvas edge"
  - name: "dotSize"
    type: "number"
    default: "0.3"
    description: "The size of each dot, zero (0) being invisible, one (1) filled the grid with no gaps"
  - name: "twinkle"
    type: "number"
    default: "0"
    description: "Intensity of the twinkle effect (0 = off, 1 = full twinkle)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <DotGrid
    color="#ffffff"
  />
</Shader>
```

```jsx
<Shader>
  <DotGrid
    color="#ffffff"
  />
</Shader>
```

```svelte
<Shader>
  <DotGrid
    color="#ffffff"
  />
</Shader>
```

```tsx
<Shader>
  <DotGrid
    color="#ffffff"
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'DotGrid', props: { color: '#ffffff' } }
  ]
})
```
::
