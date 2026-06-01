---
title: AngularBlur
description: Radial motion blur rotating around a center point
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# AngularBlur

Radial motion blur rotating around a center point


::shader-preview{component="AngularBlur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "20"
    description: "Intensity of the angular blur effect"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the rotation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <AngularBlur
    :intensity="20"
  >
    <Circle />
  </AngularBlur>
</Shader>
```

```jsx
<Shader>
  <AngularBlur
    intensity={20}
  >
    <Circle />
  </AngularBlur>
</Shader>
```

```svelte
<Shader>
  <AngularBlur
    intensity={20}
  >
    <Circle />
  </AngularBlur>
</Shader>
```

```tsx
<Shader>
  <AngularBlur
    intensity={20}
  >
    <Circle />
  </AngularBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'AngularBlur', props: { intensity: 20 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
