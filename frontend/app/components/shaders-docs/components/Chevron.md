---
title: Chevron
description: Animated chevron / zigzag stripe pattern
category: Textures
componentType: Generator
requiresChild: false
---

# Chevron

Animated chevron / zigzag stripe pattern


::shader-preview{component="Chevron"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "First color"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Second color"
  - name: "count"
    type: "number"
    default: "5"
    description: "Number of chevron pairs visible"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Rotation angle of the chevrons"
  - name: "balance"
    type: "number"
    default: "0.5"
    description: "Ratio of the two colors"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Edge softness"
  - name: "speed"
    type: "number"
    default: "0"
    description: "Animation speed"
  - name: "offset"
    type: "number"
    default: "0"
    description: "Phase offset for pattern positioning"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Chevron />
</Shader>
```

```jsx
<Shader>
  <Chevron />
</Shader>
```

```svelte
<Shader>
  <Chevron />
</Shader>
```

```tsx
<Shader>
  <Chevron />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Chevron', props: {} }
  ]
})
```
::
