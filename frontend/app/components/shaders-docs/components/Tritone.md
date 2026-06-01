---
title: Tritone
description: "Map colors to three tones: shadows, midtones, highlights"
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Tritone

Map colors to three tones: shadows, midtones, highlights


::shader-preview{component="Tritone"}
::

## Props

::props-table
---
props:
  - name: "colorA"
    type: "string"
    default: "#ce1bea"
    description: "First color (used for shadows/darkest areas)"
  - name: "colorB"
    type: "string"
    default: "#2fff00"
    description: "Second color (used for midtones)"
  - name: "colorC"
    type: "string"
    default: "#ffff00"
    description: "Third color (used for highlights/brightest areas)"
  - name: "blendMid"
    type: "number"
    default: "0.5"
    description: "Midpoint position between the three colors"
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
  <Tritone>
    <Circle />
  </Tritone>
</Shader>
```

```jsx
<Shader>
  <Tritone>
    <Circle />
  </Tritone>
</Shader>
```

```svelte
<Shader>
  <Tritone>
    <Circle />
  </Tritone>
</Shader>
```

```tsx
<Shader>
  <Tritone>
    <Circle />
  </Tritone>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Tritone', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
