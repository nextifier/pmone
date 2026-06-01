---
title: LinearGradient
description: Create smooth linear color gradients
category: Textures
componentType: Generator
requiresChild: false
---

# LinearGradient

Create smooth linear color gradients


::shader-preview{component="LinearGradient"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#1aff00"
    description: "The starting color of the gradient"
  - name: "colorB"
    type: "string"
    default: "#0000ff"
    description: "The ending color of the gradient"
  - name: "start"
    type: "{x: number, y: number}"
    default: "{\"x\":0,\"y\":0.5}"
    description: "The starting point of the gradient"
  - name: "end"
    type: "{x: number, y: number}"
    default: "{\"x\":1,\"y\":0.5}"
    description: "The ending point of the gradient"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Additional rotation angle of the gradient (in degrees)"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle areas beyond the gradient endpoints"
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
  <LinearGradient />
</Shader>
```

```jsx
<Shader>
  <LinearGradient />
</Shader>
```

```svelte
<Shader>
  <LinearGradient />
</Shader>
```

```tsx
<Shader>
  <LinearGradient />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'LinearGradient', props: {} }
  ]
})
```
::
