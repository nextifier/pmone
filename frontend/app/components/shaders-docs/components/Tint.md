---
title: Tint
description: Apply a color tint to the image
category: Adjustments
componentType: Filter/Effect
requiresChild: true
---

# Tint

Apply a color tint to the image


::shader-preview{component="Tint"}
::

## Props

::props-table
---
props:
  - name: "color"
    type: "string"
    default: "#ff8800"
    description: "Tint color"
  - name: "amount"
    type: "number"
    default: "0.5"
    description: "Tint amount (0 = no tint, 1 = full tint)"
  - name: "preserveLuminosity"
    type: "boolean"
    default: "true"
    description: "Preserve original brightness"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Tint
    color="#ff8800"
  >
    <Circle />
  </Tint>
</Shader>
```

```jsx
<Shader>
  <Tint
    color="#ff8800"
  >
    <Circle />
  </Tint>
</Shader>
```

```svelte
<Shader>
  <Tint
    color="#ff8800"
  >
    <Circle />
  </Tint>
</Shader>
```

```tsx
<Shader>
  <Tint
    color="#ff8800"
  >
    <Circle />
  </Tint>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Tint', props: { color: '#ff8800' }, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
