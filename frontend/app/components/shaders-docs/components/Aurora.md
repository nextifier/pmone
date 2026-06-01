---
title: Aurora
description: Mesmerizing aurora borealis with layered curtains, vertical rays, and flowing light.
category: Textures
componentType: Generator
requiresChild: false
---

# Aurora

Mesmerizing aurora borealis with layered curtains, vertical rays, and flowing light.


::shader-preview{component="Aurora"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#a533f8"
    description: "Edge color at the curtain base"
  - name: "colorB"
    type: "string"
    default: "#22ee88"
    description: "Core color in the bright center"
  - name: "colorC"
    type: "string"
    default: "#1694e8"
    description: "Tip color at the ray ends"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
  - name: "balance"
    type: "number"
    default: "50"
    description: "Shifts color distribution across the curtain height"
  - name: "intensity"
    type: "number"
    default: "80"
    description: "Overall aurora brightness"
  - name: "curtainCount"
    type: "number"
    default: "4"
    description: "Number of aurora curtain layers"
  - name: "speed"
    type: "number"
    default: "5"
    description: "Animation speed"
  - name: "waviness"
    type: "number"
    default: "50"
    description: "How much the curtains undulate"
  - name: "rayDensity"
    type: "number"
    default: "20"
    description: "Density of vertical ray structures"
  - name: "height"
    type: "number"
    default: "120"
    description: "How tall the aurora extends"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0}"
    description: "Center position of the aurora"
  - name: "seed"
    type: "number"
    default: "0"
    description: "Random seed for variation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Aurora
    :intensity="80"
  />
</Shader>
```

```jsx
<Shader>
  <Aurora
    intensity={80}
  />
</Shader>
```

```svelte
<Shader>
  <Aurora
    intensity={80}
  />
</Shader>
```

```tsx
<Shader>
  <Aurora
    intensity={80}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Aurora', props: { intensity: 80 } }
  ]
})
```
::
