---
title: WaveDistortion
description: Wave-based distortion with multiple waveform types
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# WaveDistortion

Wave-based distortion with multiple waveform types


::shader-preview{component="WaveDistortion"}
::

## Props

::props-table
---
props:
  - name: "strength"
    type: "number"
    default: "0.3"
    description: "Distortion intensity"
  - name: "frequency"
    type: "number"
    default: "1"
    description: "Number of bends/waves"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed"
  - name: "angle"
    type: "number"
    default: "0"
    description: "Direction of wave distortion in degrees"
  - name: "waveType"
    type: "\"sine\" | \"triangle\" | \"square\" | \"sawtooth\" | \"bounce\""
    default: "sine"
    description: "Shape of the distortion wave"
  - name: "edges"
    type: "\"stretch\" | \"transparent\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle edges when distortion pushes content out of bounds"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <WaveDistortion>
    <Circle />
  </WaveDistortion>
</Shader>
```

```jsx
<Shader>
  <WaveDistortion>
    <Circle />
  </WaveDistortion>
</Shader>
```

```svelte
<Shader>
  <WaveDistortion>
    <Circle />
  </WaveDistortion>
</Shader>
```

```tsx
<Shader>
  <WaveDistortion>
    <Circle />
  </WaveDistortion>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'WaveDistortion', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
