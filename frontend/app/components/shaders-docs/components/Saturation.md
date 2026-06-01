---
title: Saturation
description: Adjust color saturation intensity
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Saturation

Adjust color saturation intensity


::shader-preview{component="Saturation"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "1"
    description: "The intensity of the saturation effect (1 being no change)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Saturation
    :intensity="1"
  >
    <Circle />
  </Saturation>
</Shader>
```

```jsx
<Shader>
  <Saturation
    intensity={1}
  >
    <Circle />
  </Saturation>
</Shader>
```

```svelte
<Shader>
  <Saturation
    intensity={1}
  >
    <Circle />
  </Saturation>
</Shader>
```

```tsx
<Shader>
  <Saturation
    intensity={1}
  >
    <Circle />
  </Saturation>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Saturation', props: { intensity: 1 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
