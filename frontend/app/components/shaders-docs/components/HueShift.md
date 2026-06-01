---
title: HueShift
description: Rotate hue around the color wheel
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# HueShift

Rotate hue around the color wheel


::shader-preview{component="HueShift"}
::

## Props

::props-table
---
props:
  - name: "shift"
    type: "number"
    default: "0"
    description: "The amount to shift the hue by"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <HueShift>
    <Circle />
  </HueShift>
</Shader>
```

```jsx
<Shader>
  <HueShift>
    <Circle />
  </HueShift>
</Shader>
```

```svelte
<Shader>
  <HueShift>
    <Circle />
  </HueShift>
</Shader>
```

```tsx
<Shader>
  <HueShift>
    <Circle />
  </HueShift>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'HueShift', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
