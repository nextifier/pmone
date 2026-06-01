---
title: Stretch
description: Stretch content towards a direction from a center point
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Stretch

Stretch content towards a direction from a center point


::shader-preview{component="Stretch"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the stretch effect"
  - name: "strength"
    type: "number"
    default: "1"
    description: "The intensity of the stretch effect"
  - name: "angle"
    type: "number"
    default: "0"
    description: "The direction of the stretch in degrees"
  - name: "falloff"
    type: "number"
    default: "0"
    description: "Controls the sharpness of the transition (0 = sharp edge, 1 = gradual transition)"
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
  <Stretch>
    <Circle />
  </Stretch>
</Shader>
```

```jsx
<Shader>
  <Stretch>
    <Circle />
  </Stretch>
</Shader>
```

```svelte
<Shader>
  <Stretch>
    <Circle />
  </Stretch>
</Shader>
```

```tsx
<Shader>
  <Stretch>
    <Circle />
  </Stretch>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Stretch', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
