---
title: Blur
description: A simple Gaussian blur effect
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# Blur

A simple Gaussian blur effect


::shader-preview{component="Blur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "50"
    description: "Intensity of the blur effect"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Blur
    :intensity="50"
  >
    <Circle />
  </Blur>
</Shader>
```

```jsx
<Shader>
  <Blur
    intensity={50}
  >
    <Circle />
  </Blur>
</Shader>
```

```svelte
<Shader>
  <Blur
    intensity={50}
  >
    <Circle />
  </Blur>
</Shader>
```

```tsx
<Shader>
  <Blur
    intensity={50}
  >
    <Circle />
  </Blur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Blur', props: { intensity: 50 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
