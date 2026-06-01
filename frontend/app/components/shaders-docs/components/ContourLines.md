---
title: ContourLines
description: Draw topographical contour lines based on luminance or alpha
category: Stylize
componentType: Filter/Effect
requiresChild: true
---

# ContourLines

Draw topographical contour lines based on luminance or alpha


::shader-preview{component="ContourLines"}
::

## Props

::props-table
---
props:
  - name: "levels"
    type: "number"
    default: "5"
    description: "Number of contour levels"
  - name: "lineWidth"
    type: "number"
    default: "2"
    description: "Width of the contour lines in pixels"
  - name: "softness"
    type: "number"
    default: "0"
    description: "Edge softness of the lines (0 = sharp, 1 = soft)"
  - name: "gamma"
    type: "number"
    default: "0.5"
    description: "Contour distribution. <1 clusters in bright, >1 clusters in dark"
  - name: "invert"
    type: "boolean"
    default: "false"
    description: "Invert the source values"
  - name: "source"
    type: "\"luminance\" | \"alpha\""
    default: "luminance"
    description: "Use luminance or alpha channel for contours"
  - name: "colorMode"
    type: "\"source\" | \"custom\""
    default: "source"
    description: "Use source image colors or custom colors"
  - name: "lineColor"
    type: "string"
    default: "#000000"
    description: "Color of the contour lines (custom mode)"
  - name: "backgroundColor"
    type: "string"
    default: "transparent"
    description: "Background color (custom mode)"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ContourLines>
    <Circle />
  </ContourLines>
</Shader>
```

```jsx
<Shader>
  <ContourLines>
    <Circle />
  </ContourLines>
</Shader>
```

```svelte
<Shader>
  <ContourLines>
    <Circle />
  </ContourLines>
</Shader>
```

```tsx
<Shader>
  <ContourLines>
    <Circle />
  </ContourLines>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ContourLines', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
