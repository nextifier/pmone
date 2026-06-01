---
title: Invert
description: Invert RGB colors while preserving alpha
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Invert

Invert RGB colors while preserving alpha


::shader-preview{component="Invert"}
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Invert>
    <Circle />
  </Invert>
</Shader>
```

```jsx
<Shader>
  <Invert>
    <Circle />
  </Invert>
</Shader>
```

```svelte
<Shader>
  <Invert>
    <Circle />
  </Invert>
</Shader>
```

```tsx
<Shader>
  <Invert>
    <Circle />
  </Invert>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Invert', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
