---
title: FilmGrain
description: Analog film grain texture overlay, weighted toward darker areas
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# FilmGrain

Analog film grain texture overlay, weighted toward darker areas


::shader-preview{component="FilmGrain"}
::

## Props

::props-table
---
props:
  - name: "strength"
    type: "number"
    default: "0.5"
    description: "Intensity of the film grain noise"
  - name: "bias"
    type: "number"
    default: "2"
    description: "Concentrates grain in darker areas. Higher values focus grain more heavily on shadows; 0 applies grain uniformly."
  - name: "animated"
    type: "boolean"
    default: "false"
    description: "When enabled, the grain pattern changes each frame for a dynamic film effect"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <FilmGrain>
    <Circle />
  </FilmGrain>
</Shader>
```

```jsx
<Shader>
  <FilmGrain>
    <Circle />
  </FilmGrain>
</Shader>
```

```svelte
<Shader>
  <FilmGrain>
    <Circle />
  </FilmGrain>
</Shader>
```

```tsx
<Shader>
  <FilmGrain>
    <Circle />
  </FilmGrain>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'FilmGrain', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
