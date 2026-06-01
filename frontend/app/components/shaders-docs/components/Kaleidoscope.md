---
title: Kaleidoscope
description: Create a kaleidoscope effect with radial mirrored segments
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Kaleidoscope

Create a kaleidoscope effect with radial mirrored segments


::shader-preview{component="Kaleidoscope"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the kaleidoscope effect"
  - name: "segments"
    type: "number"
    default: "6"
    description: "Number of radial segments in the kaleidoscope"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Rotation offset for the entire kaleidoscope pattern"
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
  <Kaleidoscope>
    <Circle />
  </Kaleidoscope>
</Shader>
```

```jsx
<Shader>
  <Kaleidoscope>
    <Circle />
  </Kaleidoscope>
</Shader>
```

```svelte
<Shader>
  <Kaleidoscope>
    <Circle />
  </Kaleidoscope>
</Shader>
```

```tsx
<Shader>
  <Kaleidoscope>
    <Circle />
  </Kaleidoscope>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Kaleidoscope', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
