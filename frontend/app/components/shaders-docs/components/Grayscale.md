---
title: Grayscale
description: Convert colors to black and white
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Grayscale

Convert colors to black and white


::shader-preview{component="Grayscale"}
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Grayscale>
    <Circle />
  </Grayscale>
</Shader>
```

```jsx
<Shader>
  <Grayscale>
    <Circle />
  </Grayscale>
</Shader>
```

```svelte
<Shader>
  <Grayscale>
    <Circle />
  </Grayscale>
</Shader>
```

```tsx
<Shader>
  <Grayscale>
    <Circle />
  </Grayscale>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Grayscale', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
