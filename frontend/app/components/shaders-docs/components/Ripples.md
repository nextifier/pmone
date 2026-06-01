---
title: Ripples
description: Concentric animated ripples emanating from a point
category: Textures
componentType: Generator
requiresChild: false
---

# Ripples

Concentric animated ripples emanating from a point


::shader-preview{component="Ripples"}
::

## Props

::props-table
---
props:
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "The center point where ripples emanate from"
  - name: "colorA"
    type: "string"
    default: "#ffffff"
    description: "Color of the ripple waves"
  - name: "colorB"
    type: "string"
    default: "#000000"
    description: "Background color between ripples"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Speed of ripple animation"
  - name: "frequency"
    type: "number"
    default: "20"
    description: "Number of ripples/spacing between them"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Softness of ripple edges"
  - name: "thickness"
    type: "number"
    default: "0.5"
    description: "Thickness of each ripple band"
  - name: "phase"
    type: "number"
    default: "0"
    description: "Phase offset for ripple animation"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Ripples />
</Shader>
```

```jsx
<Shader>
  <Ripples />
</Shader>
```

```svelte
<Shader>
  <Ripples />
</Shader>
```

```tsx
<Shader>
  <Ripples />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Ripples', props: {} }
  ]
})
```
::
