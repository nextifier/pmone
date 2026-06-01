---
title: ReflectivePlane
description: Reflective floor that mirrors the content above it
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# ReflectivePlane

Reflective floor that mirrors the content above it


::shader-preview{component="ReflectivePlane"}
::

## Props

::props-table
---
props:
  - name: "height"
    type: "number"
    default: "0.7"
    description: "Vertical position of the reflective surface"
  - name: "distance"
    type: "number"
    default: "0.5"
    description: "How far below the floor the reflection remains visible before fully fading to transparent."
  - name: "falloff"
    type: "number"
    default: "0.5"
    description: "Width of the fade zone, as a fraction of reflection distance."
  - name: "blur"
    type: "number"
    default: "3"
    description: "Maximum blur applied to the reflection far from the surface."
  - name: "blurDistance"
    type: "number"
    default: "0.3"
    description: "How far below the surface the blur takes to ramp from sharp to maximum."
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle reflected samples that fall outside the source content."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ReflectivePlane>
    <Circle />
  </ReflectivePlane>
</Shader>
```

```jsx
<Shader>
  <ReflectivePlane>
    <Circle />
  </ReflectivePlane>
</Shader>
```

```svelte
<Shader>
  <ReflectivePlane>
    <Circle />
  </ReflectivePlane>
</Shader>
```

```tsx
<Shader>
  <ReflectivePlane>
    <Circle />
  </ReflectivePlane>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ReflectivePlane', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
