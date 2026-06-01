---
title: Sharpness
description: Adjust image sharpness using a convolution kernel
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Sharpness

Adjust image sharpness using a convolution kernel


::shader-preview{component="Sharpness"}
::

## Props

::props-table
---
props:
  - name: "sharpness"
    type: "number"
    default: "0"
    description: "How sharp to make the underlying image"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Sharpness>
    <Circle />
  </Sharpness>
</Shader>
```

```jsx
<Shader>
  <Sharpness>
    <Circle />
  </Sharpness>
</Shader>
```

```svelte
<Shader>
  <Sharpness>
    <Circle />
  </Sharpness>
</Shader>
```

```tsx
<Shader>
  <Sharpness>
    <Circle />
  </Sharpness>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Sharpness', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
