---
title: RadialGradient
description: Radial gradient radiating from a center point
category: Textures
componentType: Generator
requiresChild: false
---

# RadialGradient

Radial gradient radiating from a center point


::shader-preview{component="RadialGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ff0000"
    description: "The starting color at the center of the gradient"
  - name: "colorB"
    type: "string"
    default: "#0000ff"
    description: "The ending color at the edge of the gradient"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point of the radial gradient"
  - name: "radius"
    type: "number"
    default: "1"
    description: "The radius of the gradient (normalized, 0.0-1.0)"
  - name: "repeat"
    type: "number"
    default: "1"
    description: "Number of times the gradient repeats. Values above 1 create concentric rings."
  - name: "aspect"
    type: "number"
    default: "1"
    description: "Stretches the gradient into an ellipse. Values below 1 compress vertically, above 1 compress horizontally."
  - name: "skewAngle"
    type: "number"
    default: "0"
    description: "Rotates the ellipse axis in degrees. Only visible when Aspect is not 1."
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <RadialGradient
    :radius="1"
  />
</Shader>
```

```jsx
<Shader>
  <RadialGradient
    radius={1}
  />
</Shader>
```

```svelte
<Shader>
  <RadialGradient
    radius={1}
  />
</Shader>
```

```tsx
<Shader>
  <RadialGradient
    radius={1}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'RadialGradient', props: { radius: 1 } }
  ]
})
```
::
