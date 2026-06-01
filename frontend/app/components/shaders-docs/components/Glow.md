---
title: Glow
description: Soft glow effect with adjustable intensity
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# Glow

Soft glow effect with adjustable intensity


::shader-preview{component="Glow"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "1"
    description: "Glow intensity (brightness of the glow effect)"
  - name: "threshold"
    type: "number"
    default: "0.5"
    description: "Brightness threshold for glow extraction (lower = more glow)"
  - name: "size"
    type: "number"
    default: "25"
    description: "Glow spread in pixels (clean up to ~72px, mild banding above)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Glow
    :intensity="1"
  >
    <Circle />
  </Glow>
</Shader>
```

```jsx
<Shader>
  <Glow
    intensity={1}
  >
    <Circle />
  </Glow>
</Shader>
```

```svelte
<Shader>
  <Glow
    intensity={1}
  >
    <Circle />
  </Glow>
</Shader>
```

```tsx
<Shader>
  <Glow
    intensity={1}
  >
    <Circle />
  </Glow>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Glow', props: { intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
