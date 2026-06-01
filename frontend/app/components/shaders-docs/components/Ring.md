---
title: Ring
description: Annular ring (donut) with adjustable radius and band thickness
category: Shapes
componentType: Generator
requiresChild: false
---

# Ring

Annular ring (donut) with adjustable radius and band thickness


::shader-preview{component="Ring"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ffffff"
    description: "Fill color of the ring"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the ring"
  - name: "radius"
    type: "number"
    default: "0.3"
    description: "Distance from center to the ring's midline in UV space"
  - name: "thickness"
    type: "number"
    default: "0.07"
    description: "Half-width of the ring band - total ring width is twice this value"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Edge softness for antialiasing (applied to both inner and outer ring edges)"
  - name: "strokeThickness"
    type: "number"
    default: "0"
    description: "Stroke thickness. Zero means no stroke."
  - name: "strokeColor"
    type: "string"
    default: "#000000"
    description: "Color of the stroke outline"
  - name: "strokePosition"
    type: "\"outside\" | \"center\" | \"inside\""
    default: "center"
    description: "Position of the stroke relative to the ring edge"
  - name: "colorSpace"
    type: "\"linear\" | \"oklch\" | \"oklab\" | \"hsl\" | \"hsv\" | \"lch\""
    default: "linear"
    description: "Color space for blending fill and stroke colors"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Ring
    color="#ffffff"
    :radius="0.3"
  />
</Shader>
```

```jsx
<Shader>
  <Ring
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```svelte
<Shader>
  <Ring
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```tsx
<Shader>
  <Ring
    color="#ffffff"
    radius={0.3}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Ring', props: { color: '#ffffff', radius: 0.3 } }
  ]
})
```
::
