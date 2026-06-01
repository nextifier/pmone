---
title: Weave
description: Interlaced textile weave pattern with two thread colors going over and under each other
category: Textures
componentType: Generator
requiresChild: false
---

# Weave

Interlaced textile weave pattern with two thread colors going over and under each other


::shader-preview{component="Weave"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#c4c4c4"
    description: "Horizontal thread color"
  - name: "colorB"
    type: "string"
    default: "#4d4d4d"
    description: "Vertical thread color"
  - name: "cells"
    type: "number"
    default: "10"
    description: "Number of threads across the shortest canvas edge"
  - name: "gap"
    type: "number"
    default: "0.25"
    description: "Gap between threads (0 = no gap, 0.5 = maximum gap)"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation of the weave pattern in degrees"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Weave />
</Shader>
```

```jsx
<Shader>
  <Weave />
</Shader>
```

```svelte
<Shader>
  <Weave />
</Shader>
```

```tsx
<Shader>
  <Weave />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Weave', props: {} }
  ]
})
```
::
