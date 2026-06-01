---
title: Solarize
description: Inverts tones above a luminance threshold - a classic darkroom and photo effect
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Solarize

Inverts tones above a luminance threshold - a classic darkroom and photo effect


::shader-preview{component="Solarize"}
::

## Props

::props-table
---
props:
  - name: "threshold"
    type: "number"
    default: "0.5"
    description: "Luminance level above which colors are inverted. Pixels brighter than this threshold get flipped."
  - name: "strength"
    type: "number"
    default: "1"
    description: "Blend between original (0) and fully solarized (1)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Solarize>
    <Circle />
  </Solarize>
</Shader>
```

```jsx
<Shader>
  <Solarize>
    <Circle />
  </Solarize>
</Shader>
```

```svelte
<Shader>
  <Solarize>
    <Circle />
  </Solarize>
</Shader>
```

```tsx
<Shader>
  <Solarize>
    <Circle />
  </Solarize>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Solarize', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
