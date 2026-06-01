---
title: ChannelBlur
description: Independent blur for red, green, and blue channels
category: Blurs
componentType: Filter/Effect
requiresChild: true
---

# ChannelBlur

Independent blur for red, green, and blue channels


::shader-preview{component="ChannelBlur"}
::

## Props

::props-table
---
props:
  - name: "redIntensity"
    type: "number"
    default: "0"
    description: "Blur intensity for red channel"
  - name: "greenIntensity"
    type: "number"
    default: "20"
    description: "Blur intensity for green channel"
  - name: "blueIntensity"
    type: "number"
    default: "40"
    description: "Blur intensity for blue channel"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <ChannelBlur>
    <Circle />
  </ChannelBlur>
</Shader>
```

```jsx
<Shader>
  <ChannelBlur>
    <Circle />
  </ChannelBlur>
</Shader>
```

```svelte
<Shader>
  <ChannelBlur>
    <Circle />
  </ChannelBlur>
</Shader>
```

```tsx
<Shader>
  <ChannelBlur>
    <Circle />
  </ChannelBlur>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'ChannelBlur', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
