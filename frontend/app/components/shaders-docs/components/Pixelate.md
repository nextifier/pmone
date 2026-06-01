---
title: Pixelate
description: Pixelation effect with adjustable cell size
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Pixelate

Pixelation effect with adjustable cell size


::shader-preview{component="Pixelate"}
::

## Props

::props-table
---
props:
  - name: "scale"
    type: "number"
    default: "50"
    description: "Number of pixels along the longest edge (higher = smaller pixels)"
  - name: "gap"
    type: "number"
    default: "0"
    description: "Space between pixels as a fraction of cell size (0 = no gap, 1 = fully invisible)"
  - name: "roundness"
    type: "number"
    default: "0"
    description: "Roundness of each pixel's corners (0 = square, 1 = circle)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Pixelate>
    <Circle />
  </Pixelate>
</Shader>
```

```jsx
<Shader>
  <Pixelate>
    <Circle />
  </Pixelate>
</Shader>
```

```svelte
<Shader>
  <Pixelate>
    <Circle />
  </Pixelate>
</Shader>
```

```tsx
<Shader>
  <Pixelate>
    <Circle />
  </Pixelate>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Pixelate', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
