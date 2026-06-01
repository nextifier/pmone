---
title: BrightnessContrast
description: Adjust brightness and contrast of the image
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# BrightnessContrast

Adjust brightness and contrast of the image


::shader-preview{component="BrightnessContrast"}
::

## Props

::props-table
---
props:
  - name: "brightness"
    type: "number"
    default: "0"
    description: "Brightness adjustment (-1 to 1)"
  - name: "contrast"
    type: "number"
    default: "0"
    description: "Contrast adjustment (-1 to 1)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <BrightnessContrast>
    <Circle />
  </BrightnessContrast>
</Shader>
```

```jsx
<Shader>
  <BrightnessContrast>
    <Circle />
  </BrightnessContrast>
</Shader>
```

```svelte
<Shader>
  <BrightnessContrast>
    <Circle />
  </BrightnessContrast>
</Shader>
```

```tsx
<Shader>
  <BrightnessContrast>
    <Circle />
  </BrightnessContrast>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'BrightnessContrast', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
