---
title: Shatter
description: Broken glass effect with tectonic plate displacement
category: Interactive
componentType: Filter/Effect
requiresChild: true
---

# Shatter

Broken glass effect with tectonic plate displacement


::shader-preview{component="Shatter"}
::

## Props

::props-table
---
props:
  - name: "crackWidth"
    type: "number"
    default: "1"
    description: "Thickness of crack lines"
  - name: "intensity"
    type: "number"
    default: "4"
    description: "How much shards shift"
  - name: "radius"
    type: "number"
    default: "0.4"
    description: "Cursor influence radius"
  - name: "decay"
    type: "number"
    default: "1"
    description: "How fast shards return to rest"
  - name: "seed"
    type: "number"
    default: "2"
    description: "Random seed for pattern"
  - name: "chromaticSplit"
    type: "number"
    default: "1"
    description: "RGB separation for prismatic glass effect"
  - name: "refractionStrength"
    type: "number"
    default: "5"
    description: "How much cracks bend/distort the underlying image"
  - name: "shardLighting"
    type: "number"
    default: "0.1"
    description: "Subtle lighting on tilted shards for 3D depth"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "mirror"
    description: "How to handle edges when displacement pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Shatter
    :intensity="4"
    :radius="0.4"
  >
    <Circle />
  </Shatter>
</Shader>
```

```jsx
<Shader>
  <Shatter
    intensity={4}
    radius={0.4}
  >
    <Circle />
  </Shatter>
</Shader>
```

```svelte
<Shader>
  <Shatter
    intensity={4}
    radius={0.4}
  >
    <Circle />
  </Shatter>
</Shader>
```

```tsx
<Shader>
  <Shatter
    intensity={4}
    radius={0.4}
  >
    <Circle />
  </Shatter>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Shatter', props: { intensity: 4, radius: 0.4 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
