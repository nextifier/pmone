---
title: Posterize
description: Reduce color depth to create a poster effect
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Posterize

Reduce color depth to create a poster effect


::shader-preview{component="Posterize"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "5"
    description: "The intensity of the posterization effect (lower is more posterized)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Posterize
    :intensity="5"
  >
    <Circle />
  </Posterize>
</Shader>
```

```jsx
<Shader>
  <Posterize
    intensity={5}
  >
    <Circle />
  </Posterize>
</Shader>
```

```svelte
<Shader>
  <Posterize
    intensity={5}
  >
    <Circle />
  </Posterize>
</Shader>
```

```tsx
<Shader>
  <Posterize
    intensity={5}
  >
    <Circle />
  </Posterize>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Posterize', props: { intensity: 5 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
