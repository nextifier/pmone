---
title: Form3D
description: Wraps child content onto a 3D raymarched shape with lighting.
category: Distortions
componentType: Filter/Effect
requiresChild: true
---

# Form3D

Wraps child content onto a 3D raymarched shape with lighting.


::shader-preview{component="Form3D"}
::

## Props

::props-table
---
props:
  - name: "shape3d"
    type: "string"
    default: "{\"type\":\"ribbon\",\"angle\":0,\"twist\":50,\"width\":40,\"thickness\":20,\"seed\":0}"
    description: "3D shape and its parameters"
  - name: "center"
    type: "{x: number, y: number}"
    default: "{\"x\":0.5,\"y\":0.5}"
    description: "Center position of the shape on screen"
  - name: "zoom"
    type: "number"
    default: "50"
    description: "Camera zoom level"
  - name: "glossiness"
    type: "number"
    default: "50"
    description: "Specular highlight intensity and sharpness"
  - name: "lighting"
    type: "number"
    default: "50"
    description: "Overall intensity of lighting effects"
  - name: "uvMode"
    type: "\"stretch\" | \"mirror\" | \"wrap\""
    default: "stretch"
    description: "How to handle UV coordinates at shape boundaries"
  - name: "speed"
    type: "number"
    default: "1"
    description: "Animation speed - scales all spin rates"
---
::

## Usage

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <Form3D>
    <Circle />
  </Form3D>
</Shader>
```

```jsx
<Shader>
  <Form3D>
    <Circle />
  </Form3D>
</Shader>
```

```svelte
<Shader>
  <Form3D>
    <Circle />
  </Form3D>
</Shader>
```

```tsx
<Shader>
  <Form3D>
    <Circle />
  </Form3D>
</Shader>
```

```javascript
import { createShader } from 'shaders/js'

const shader = await createShader(canvas, {
  components: [
    { type: 'Form3D', props: {}, children: [
      { type: 'Circle', props: {} }
    ]}
  ]
})
```
::
