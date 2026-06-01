---
title: GlassTiles
description: Refraction-like distortion in a tile grid pattern
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# GlassTiles

Refraction-like distortion in a tile grid pattern


::shader-preview{component="GlassTiles"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "2"
    description: "The intensity of the glass tiles effect"
  - name: "tileCount"
    type: "number"
    default: "20"
    description: "Number of tiles across the longest dimension"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation angle of the tile grid in degrees"
  - name: "roundness"
    type: "number"
    default: "0"
    description: "Makes tiles more circular instead of square"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <GlassTiles
    :intensity="2"
  >
    <Circle />
  </GlassTiles>
</Shader>
```

```jsx
<Shader>
  <GlassTiles
    intensity={2}
  >
    <Circle />
  </GlassTiles>
</Shader>
```

```svelte
<Shader>
  <GlassTiles
    intensity={2}
  >
    <Circle />
  </GlassTiles>
</Shader>
```

```tsx
<Shader>
  <GlassTiles
    intensity={2}
  >
    <Circle />
  </GlassTiles>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'GlassTiles', props: { intensity: 2 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
