---
title: Paper
description: Applies realistic paper grain and surface roughness to child content
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Paper

Applies realistic paper grain and surface roughness to child content


::shader-preview{component="Paper"}
::

## Props

::props-table
---
props:
  - name: "roughness"
    type: "number"
    default: "0.3"
    description: "Surface roughness - higher values create more pronounced brightness variation"
  - name: "grainScale"
    type: "number"
    default: "1"
    description: "Scale of the paper grain - lower = coarser, higher = finer"
  - name: "displacement"
    type: "number"
    default: "0.15"
    description: "Surface micro-roughness - shifts pixels at grain scale like real paper fiber bumps"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for pattern variation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Paper>
    <Circle />
  </Paper>
</Shader>
```

```jsx
<Shader>
  <Paper>
    <Circle />
  </Paper>
</Shader>
```

```svelte
<Shader>
  <Paper>
    <Circle />
  </Paper>
</Shader>
```

```tsx
<Shader>
  <Paper>
    <Circle />
  </Paper>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Paper', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
