---
title: VHS
description: Analog VHS tape with intermittent tape damage, chroma bleed, and per-scanline noise
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# VHS

Analog VHS tape with intermittent tape damage, chroma bleed, and per-scanline noise


::shader-preview{component="VHS"}
::

## Props

::props-table
---
props:
  - name: "wobble"
    type: "number"
    default: "1"
    description: "Overall amount of tape damage - waves, creases, and head-switching noise. Bursts on and off organically over time."
  - name: "scanlineNoise"
    type: "number"
    default: "0.6"
    description: "Per-scanline fine chroma/luma jitter. Adds the classic horizontal-streak detail."
  - name: "smear"
    type: "number"
    default: "0.2"
    description: "Horizontal chroma smear (color bleed) amount. Positive trails colour to the right (classic VHS), negative trails it to the left."
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed of the tape effects."
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <VHS>
    <Circle />
  </VHS>
</Shader>
```

```jsx
<Shader>
  <VHS>
    <Circle />
  </VHS>
</Shader>
```

```svelte
<Shader>
  <VHS>
    <Circle />
  </VHS>
</Shader>
```

```tsx
<Shader>
  <VHS>
    <Circle />
  </VHS>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'VHS', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
