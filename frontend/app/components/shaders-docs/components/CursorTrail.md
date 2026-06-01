---
title: CursorTrail
description: Animated trail effect that tracks cursor movement
category: Interactive
componentType: Generator
requiresChild: false
---

# CursorTrail

Animated trail effect that tracks cursor movement


::shader-preview{component="CursorTrail"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#00aaff"
    description: "Color of fresh trails"
  - name: "colorB"
    type: "string"
    default: "#ff00aa"
    description: "Color trails transition to as they fade"
  - name: "radius"
    type: "number"
    default: "0.5"
    description: "Base radius of trail circles"
  - name: "length"
    type: "number"
    default: "0.5"
    description: "How long trail circles persist (in seconds)"
  - name: "shrink"
    type: "number"
    default: "1"
    description: "How much circles shrink as they fade out (0 = no shrink, 1 = full shrink)"
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
  <CursorTrail
    :radius="0.5"
  />
</Shader>
```

```jsx
<Shader>
  <CursorTrail
    radius={0.5}
  />
</Shader>
```

```svelte
<Shader>
  <CursorTrail
    radius={0.5}
  />
</Shader>
```

```tsx
<Shader>
  <CursorTrail
    radius={0.5}
  />
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'CursorTrail', props: { radius: 0.5 } }
  ]
})
```
::
