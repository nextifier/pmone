---
title: ZoomBlur
description: Radial zoom blur expanding from a center point
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# ZoomBlur

Radial zoom blur expanding from a center point


::shader-preview{component="ZoomBlur"}
::

## Props

::props-table
---
props:
  - name: "intensity"
    type: "number"
    default: "30"
    description: "Intensity of the zoom blur effect"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the zoom blur"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ZoomBlur
    :intensity="30"
  >
    <Circle />
  </ZoomBlur>
</Shader>
```

```jsx
<Shader>
  <ZoomBlur
    intensity={30}
  >
    <Circle />
  </ZoomBlur>
</Shader>
```

```svelte
<Shader>
  <ZoomBlur
    intensity={30}
  >
    <Circle />
  </ZoomBlur>
</Shader>
```

```tsx
<Shader>
  <ZoomBlur
    intensity={30}
  >
    <Circle />
  </ZoomBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ZoomBlur', props: { intensity: 30 }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
