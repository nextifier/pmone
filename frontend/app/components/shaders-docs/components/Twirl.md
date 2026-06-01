---
title: Twirl
description: Rotate and twist content around a center point
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Twirl

Rotate and twist content around a center point


::shader-preview{component="Twirl"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the twirl effect"
  - name: "intensity"
    type: "number"
    default: "1"
    description: "The strength of the twirl effect"
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
  <Twirl
    :intensity="1"
  >
    <Circle />
  </Twirl>
</Shader>
```

```jsx
<Shader>
  <Twirl
    intensity={1}
  >
    <Circle />
  </Twirl>
</Shader>
```

```svelte
<Shader>
  <Twirl
    intensity={1}
  >
    <Circle />
  </Twirl>
</Shader>
```

```tsx
<Shader>
  <Twirl
    intensity={1}
  >
    <Circle />
  </Twirl>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Twirl', props: { intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
