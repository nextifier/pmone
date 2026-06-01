---
title: Vibrance
description: Selective saturation adjustment protecting skin tones
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Vibrance

Selective saturation adjustment protecting skin tones


::shader-preview{component="Vibrance"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "0"
    description: "The intensity of the vibrance effect"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Vibrance
    :intensity="0"
  >
    <Circle />
  </Vibrance>
</Shader>
```

```jsx
<Shader>
  <Vibrance
    intensity={0}
  >
    <Circle />
  </Vibrance>
</Shader>
```

```svelte
<Shader>
  <Vibrance
    intensity={0}
  >
    <Circle />
  </Vibrance>
</Shader>
```

```tsx
<Shader>
  <Vibrance
    intensity={0}
  >
    <Circle />
  </Vibrance>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Vibrance', props: { intensity: 0 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
