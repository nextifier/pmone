---
title: Liquify
description: Liquid-like interactive deformation effect
category: Interactive
componentType: Filter/Effect
requiresChild: true
---

# Liquify

Liquid-like interactive deformation effect


::shader-preview{component="Liquify"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "10"
    description: "Scale of the fabric displacement effect"
  - name: "stiffness"
    type: "number"
    default: "3"
    description: "Fabric rigidity (higher = stiffer canvas, lower = stretchy silk)"
  - name: "damping"
    type: "number"
    default: "3"
    description: "How quickly fabric motion settles"
  - name: "radius"
    type: "number"
    default: "1"
    description: "Cursor influence area"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Liquify
    :intensity="10"
    :radius="1"
  >
    <Circle />
  </Liquify>
</Shader>
```

```jsx
<Shader>
  <Liquify
    intensity={10}
    radius={1}
  >
    <Circle />
  </Liquify>
</Shader>
```

```svelte
<Shader>
  <Liquify
    intensity={10}
    radius={1}
  >
    <Circle />
  </Liquify>
</Shader>
```

```tsx
<Shader>
  <Liquify
    intensity={10}
    radius={1}
  >
    <Circle />
  </Liquify>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Liquify', props: { intensity: 10, radius: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
