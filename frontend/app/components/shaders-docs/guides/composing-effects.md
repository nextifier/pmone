---
title: Composing Effects
description: How to stack and nest components to create complex effects
icon: layer-plus
category: concepts
---

# Composing Effects

Shaders is built around a simple, predictable component structure. Once you understand it, composing complex effects stays intuitive and maintainable. The `<Shader>` component is the root of your effect. Every instance of this renders its own GPU canvas. Visual output is created by stacking child components.

## Stacking Components

Components are evaluated **top-to-bottom**, in the order they appear in your markup, similar to how regular DOM elements are rendered.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <!-- Bottom layer -->
  <LinearGradient />

  <!-- Middle layer -->
  <Circle color="#ff0088" radius="0.8" />

  <!-- Top layer -->
  <GlassTiles />
</Shader>
```

```jsx
<Shader>
  {/* Bottom layer */}
  <LinearGradient />

  {/* Middle layer */}
  <Circle color="#ff0088" radius={0.8} />

  {/* Top layer */}
  <GlassTiles />
</Shader>
```

```tsx
<Shader>
  {/* Bottom layer */}
  <LinearGradient />

  {/* Middle layer */}
  <Circle color="#ff0088" radius={0.8} />

  {/* Top layer */}
  <GlassTiles />
</Shader>
```

```tsx
<Shader>
  {/* Bottom layer */}
  <LinearGradient />

  {/* Middle layer */}
  <Circle color="#ff0088" radius={0.8} />

  {/* Top layer */}
  <GlassTiles />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    // Bottom layer
    { type: 'LinearGradient', props: {} },
    // Middle layer
    { type: 'Circle', props: { color: '#ff0088', radius: 0.8 } },
    // Top layer
    { type: 'GlassTiles', props: {} }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: Circle
      props:
        color: "#ff0088"
        radius: 0.8
    - type: GlassTiles
---
::

## Nesting Components

Notice how the `<GlassTiles>` component in the above example applies to all proceeding sibling components. If you wanted to _only_ apply glass tiles to the `<Circle>` component, you can nest it inside the `<GlassTiles>` component:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <!-- This gradient is NOT affected -->
  <LinearGradient />

  <!-- Only the Circle inside has glass tiles applied -->
  <GlassTiles>
    <Circle color="#ff0088" radius="0.8" />
  </GlassTiles>
</Shader>
```

```jsx
<Shader>
  {/* This gradient is NOT affected */}
  <LinearGradient />

  {/* Only the Circle inside has glass tiles applied */}
  <GlassTiles>
    <Circle color="#ff0088" radius={0.8} />
  </GlassTiles>
</Shader>
```

```tsx
<Shader>
  {/* This gradient is NOT affected */}
  <LinearGradient />

  {/* Only the Circle inside has glass tiles applied */}
  <GlassTiles>
    <Circle color="#ff0088" radius={0.8} />
  </GlassTiles>
</Shader>
```

```tsx
<Shader>
  {/* This gradient is NOT affected */}
  <LinearGradient />

  {/* Only the Circle inside has glass tiles applied */}
  <GlassTiles>
    <Circle color="#ff0088" radius={0.8} />
  </GlassTiles>
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    // This gradient is NOT affected
    { type: 'LinearGradient', props: {} },
    // Only the Circle inside has glass tiles applied
    { type: 'GlassTiles', props: {}, children: [
      { type: 'Circle', props: { color: '#ff0088', radius: 0.8 } }
    ]}
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: GlassTiles
      children:
        - type: Circle
          props:
            color: "#ff0088"
            radius: 0.8
---
::

You can nest as many components as you like, so long as they accept children. Generally speaking, components that generate pixels (such as `LinearGradient`) don't accept children, whereas components that apply effects (such as `GlassTiles`) do. The component reference in this documentation shows if the particular component accepts children.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader>
  <!-- Unaffected -->
  <LinearGradient />

  <!-- Nested effects apply only to Circle -->
  <GridDistortion radius="2">
    <GlassTiles>
      <Circle color="#ff0088" radius="0.8" />
    </GlassTiles>
  </GridDistortion>
</Shader>
```

```jsx
<Shader>
  {/* Unaffected */}
  <LinearGradient />

  {/* Nested effects apply only to Circle */}
  <GridDistortion radius={2}>
    <GlassTiles>
      <Circle color="#ff0088" radius={0.8} />
    </GlassTiles>
  </GridDistortion>
</Shader>
```

```tsx
<Shader>
  {/* Unaffected */}
  <LinearGradient />

  {/* Nested effects apply only to Circle */}
  <GridDistortion radius={2}>
    <GlassTiles>
      <Circle color="#ff0088" radius={0.8} />
    </GlassTiles>
  </GridDistortion>
</Shader>
```

```tsx
<Shader>
  {/* Unaffected */}
  <LinearGradient />

  {/* Nested effects apply only to Circle */}
  <GridDistortion radius={2}>
    <GlassTiles>
      <Circle color="#ff0088" radius={0.8} />
    </GlassTiles>
  </GridDistortion>
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    // Unaffected
    { type: 'LinearGradient', props: {} },
    // Nested effects apply only to Circle
    { type: 'GridDistortion', props: { radius: 2 }, children: [
      { type: 'GlassTiles', props: {}, children: [
        { type: 'Circle', props: { color: '#ff0088', radius: 0.8 } }
      ]}
    ]}
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
    - type: GridDistortion
      props:
        radius: 2
      children:
        - type: GlassTiles
          children:
            - type: Circle
              props:
                color: "#ff0088"
                radius: 0.8
---
::

