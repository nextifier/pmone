---
title: Mirror
description: Mirror content across a line defined by center point and angle
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Mirror

Mirror content across a line defined by center point and angle


::shader-preview{component="Mirror"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The point the mirror line passes through"
  - name: "angle"
    type: "number"
    default: "0"
    description: "The angle of the mirror line in degrees"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Mirror>
    <Circle />
  </Mirror>
</Shader>
```

```jsx
<Shader>
  <Mirror>
    <Circle />
  </Mirror>
</Shader>
```

```svelte
<Shader>
  <Mirror>
    <Circle />
  </Mirror>
</Shader>
```

```tsx
<Shader>
  <Mirror>
    <Circle />
  </Mirror>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Mirror', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
