---
title: GridDistortion
description: Interactive grid distortion controlled by mouse position
category: Interactive
componentType: Filter/Effect
requiresChild: true
---

# GridDistortion

Interactive grid distortion controlled by mouse position


::shader-preview{component="GridDistortion"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Strength of the distortion effect"
  - name: "decay"
    type: "number"
    default: "3"
    description: "Rate of distortion decay (higher = faster)"
  - name: "radius"
    type: "number"
    default: "1"
    description: "Radius of the distortion effect"
  - name: "gridSize"
    type: "number"
    default: "20"
    description: "Resolution of the distortion grid (higher = more detailed)"
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
  <GridDistortion
    :intensity="1"
    :radius="1"
  >
    <Circle />
  </GridDistortion>
</Shader>
```

```jsx
<Shader>
  <GridDistortion
    intensity={1}
    radius={1}
  >
    <Circle />
  </GridDistortion>
</Shader>
```

```svelte
<Shader>
  <GridDistortion
    intensity={1}
    radius={1}
  >
    <Circle />
  </GridDistortion>
</Shader>
```

```tsx
<Shader>
  <GridDistortion
    intensity={1}
    radius={1}
  >
    <Circle />
  </GridDistortion>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'GridDistortion', props: { intensity: 1, radius: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
