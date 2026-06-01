---
title: DiffuseBlur
description: Grain-like pixel displacement at random
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# DiffuseBlur

Grain-like pixel displacement at random


::shader-preview{component="DiffuseBlur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "30"
    description: "Intensity of the diffuse blur effect"
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
  <DiffuseBlur
    :intensity="30"
  >
    <Circle />
  </DiffuseBlur>
</Shader>
```

```jsx
<Shader>
  <DiffuseBlur
    intensity={30}
  >
    <Circle />
  </DiffuseBlur>
</Shader>
```

```svelte
<Shader>
  <DiffuseBlur
    intensity={30}
  >
    <Circle />
  </DiffuseBlur>
</Shader>
```

```tsx
<Shader>
  <DiffuseBlur
    intensity={30}
  >
    <Circle />
  </DiffuseBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'DiffuseBlur', props: { intensity: 30 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
