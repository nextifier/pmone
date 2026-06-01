---
title: Stripes
description: Alternating colored stripes with animation
category: Textures
componentType: Generator
requiresChild: false
---

# Stripes

Alternating colored stripes with animation


::shader-preview{component="Stripes"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#000000"
    description: "First stripe color"
  - name: "colorB"
    type: "string"
    default: "#ffffff"
    description: "Second stripe color"
  - name: "angle"
    type: "number"
    default: "45"
    description: "Angle of stripes in degrees"
  - name: "density"
    type: "number"
    default: "5"
    description: "Number of stripe pairs visible"
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
    default: "0.2"
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
  <Stripes />
</Shader>
```

```jsx
<Shader>
  <Stripes />
</Shader>
```

```svelte
<Shader>
  <Stripes />
</Shader>
```

```tsx
<Shader>
  <Stripes />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Stripes', props: {} }
  ]
})
```
::
