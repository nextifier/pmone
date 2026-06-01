---
title: Duotone
description: Map colors to two tones based on luminance
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Duotone

Map colors to two tones based on luminance


::shader-preview{component="Duotone"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ff0000"
    description: "First color (used for darker areas)"
  - name: "colorB"
    type: "string"
    default: "#023af4"
    description: "Second color (used for brighter areas)"
  - name: "blend"
    type: "number"
    default: "0.5"
    description: "Blend point between the two colors"
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
  <Duotone>
    <Circle />
  </Duotone>
</Shader>
```

```jsx
<Shader>
  <Duotone>
    <Circle />
  </Duotone>
</Shader>
```

```svelte
<Shader>
  <Duotone>
    <Circle />
  </Duotone>
</Shader>
```

```tsx
<Shader>
  <Duotone>
    <Circle />
  </Duotone>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Duotone', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
