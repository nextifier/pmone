---
title: LinearBlur
description: Directional motion blur in a specific angle
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# LinearBlur

Directional motion blur in a specific angle


::shader-preview{component="LinearBlur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "30"
    description: "Intensity of the linear blur effect"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction of the linear blur (in degrees)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <LinearBlur
    :intensity="30"
  >
    <Circle />
  </LinearBlur>
</Shader>
```

```jsx
<Shader>
  <LinearBlur
    intensity={30}
  >
    <Circle />
  </LinearBlur>
</Shader>
```

```svelte
<Shader>
  <LinearBlur
    intensity={30}
  >
    <Circle />
  </LinearBlur>
</Shader>
```

```tsx
<Shader>
  <LinearBlur
    intensity={30}
  >
    <Circle />
  </LinearBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'LinearBlur', props: { intensity: 30 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
