---
title: ConicGradient
description: Colors sweep in a full circle around a center point, like a color wheel
category: Textures
componentType: Generator
requiresChild: false
---

# ConicGradient

Colors sweep in a full circle around a center point, like a color wheel


::shader-preview{component="ConicGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#FF0080"
    description: "Starting color of the sweep"
  - name: "colorB"
    type: "string"
    default: "#00BFFF"
    description: "Ending color of the sweep (wraps back to Color A)"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center point of the sweep"
  - name: "rotation"
    type: "number"
    default: "0"
    description: "Rotation offset in degrees - shifts where Color A begins"
  - name: "repeat"
    type: "number"
    default: "1"
    description: "Number of times the gradient repeats around the circle. Values above 1 create a starburst pattern."
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "oklch"
    description: "Color space for color interpolation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ConicGradient />
</Shader>
```

```jsx
<Shader>
  <ConicGradient />
</Shader>
```

```svelte
<Shader>
  <ConicGradient />
</Shader>
```

```tsx
<Shader>
  <ConicGradient />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ConicGradient', props: {} }
  ]
})
```
::
