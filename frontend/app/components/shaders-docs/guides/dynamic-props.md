---
title: Dynamic Props
description: Animate and respond to mouse input declaratively without writing animation code
icon: sliders
category: features
---

# Dynamic Props

Dynamic props let any numeric or position prop respond to time, mouse position, or the visual output of another layer - declared directly as a prop value.

Instead of passing a static prop value, you can use a dynamic prop config:

```vue-html
<!-- Static value -->
<Circle :radius="0.5" />

<!-- Dynamic prop: animates radius automatically -->
<Circle :radius="{ type: 'auto-animate', mode: 'ping-pong', outputMin: 0.2, outputMax: 0.6 }" />
```

## auto-animate

Continuously animates a numeric prop between two values over time.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    :radius="{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.2,
      outputMax: 0.6,
      speed: 0.8,
      easing: 'sine'
    }"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.2,
      outputMax: 0.6,
      speed: 0.8,
      easing: 'sine'
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.2,
      outputMax: 0.6,
      speed: 0.8,
      easing: 'sine'
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.2,
      outputMax: 0.6,
      speed: 0.8,
      easing: 'sine'
    }}
  />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    {
      type: 'Circle',
      props: {
        color: '#6366f1',
        radius: {
          type: 'auto-animate',
          mode: 'ping-pong',
          outputMin: 0.2,
          outputMax: 0.6,
          speed: 0.8,
          easing: 'sine'
        }
      }
    }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: Circle
      props:
        color: "#6366f1"
        radius:
          type: auto-animate
          mode: ping-pong
          outputMin: 0.2
          outputMax: 0.6
          speed: 0.8
          easing: sine
---
::

**Properties:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `mode` | `'ping-pong' \| 'loop'` | required | `ping-pong` oscillates between min and max. `loop` advances from min to max then wraps. |
| `outputMin` | `number` | required | Value at the start/bottom of the range. |
| `outputMax` | `number` | required | Value at the end/top of the range. |
| `speed` | `number` | `1.0` | Cycles per second. Negative values reverse the loop direction. |
| `easing` | `string` | `'sine'` | Easing curve for `ping-pong` mode. Options: `sine`, `linear`, `quad`, `expo`, `bounce`. |

**Use cases:** Pulsing shapes, looping gradient angles, breathing glow intensities, continuous rotation with `loop` mode and `outputMin: 0, outputMax: 360`.

## mouse-position

Drives an XY position prop from the cursor location. Use this for any prop that expects `{ x, y }` coordinates, such as `center`, `position`, or `offset`.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    :radius="0.15"
    :center="{
      type: 'mouse-position',
      smoothing: 0.12,
      momentum: 0.2
    }"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <Circle
    color="#6366f1"
    radius={0.15}
    center={{
      type: 'mouse-position',
      smoothing: 0.12,
      momentum: 0.2
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={0.15}
    center={{
      type: 'mouse-position',
      smoothing: 0.12,
      momentum: 0.2
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={0.15}
    center={{
      type: 'mouse-position',
      smoothing: 0.12,
      momentum: 0.2
    }}
  />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    {
      type: 'Circle',
      props: {
        color: '#6366f1',
        radius: 0.15,
        center: {
          type: 'mouse-position',
          smoothing: 0.12,
          momentum: 0.2
        }
      }
    }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: Circle
      props:
        color: "#6366f1"
        radius: 0.15
        center:
          type: mouse-position
          smoothing: 0.12
          momentum: 0.2
---
::

**Properties:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `x` | `'mouse' \| number` | `'mouse'` | `'mouse'` tracks cursor X. A number pins it to that value (0-1). |
| `y` | `'mouse' \| number` | `'mouse'` | `'mouse'` tracks cursor Y. A number pins it to that value (0-1). |
| `invertX` | `boolean` | `false` | Flips the X direction - cursor moving right moves position left. |
| `invertY` | `boolean` | `false` | Flips the Y direction. |
| `smoothing` | `number` | `0` | Lag amount (0-1). `0` = instant, higher values = sluggish follow. |
| `momentum` | `number` | `0` | Spring bounce (0-1). `0` = no overshoot, values near 1 = springy. |
| `reach` | `number` | `1` | Displacement scale. `1` = 1:1 with cursor. `2` = twice the displacement. `0` = pinned to origin. |
| `originX` | `number` | `0.5` | X coordinate of the origin point that displacement scales from. |
| `originY` | `number` | `0.5` | Y coordinate of the origin point. |

**Pinning one axis:** Set `x` or `y` to a number to fix that axis while the other tracks the mouse:

```vue-html
<!-- Tracks only horizontal movement, locked at vertical center -->
<Circle :radius="0.15" :center="{ type: 'mouse-position', y: 0.5 }" />
```

## mouse

Drives a single numeric prop from the cursor's X or Y axis position.

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    :radius="{
      type: 'mouse',
      axis: 'x',
      outputMin: 0.1,
      outputMax: 0.7,
      smoothing: 0.1
    }"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'mouse',
      axis: 'x',
      outputMin: 0.1,
      outputMax: 0.7,
      smoothing: 0.1
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'mouse',
      axis: 'x',
      outputMin: 0.1,
      outputMax: 0.7,
      smoothing: 0.1
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <Circle
    color="#6366f1"
    radius={{
      type: 'mouse',
      axis: 'x',
      outputMin: 0.1,
      outputMax: 0.7,
      smoothing: 0.1
    }}
  />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    {
      type: 'Circle',
      props: {
        color: '#6366f1',
        radius: {
          type: 'mouse',
          axis: 'x',
          outputMin: 0.1,
          outputMax: 0.7,
          smoothing: 0.1
        }
      }
    }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: Circle
      props:
        color: "#6366f1"
        radius:
          type: mouse
          axis: x
          outputMin: 0.1
          outputMax: 0.7
          smoothing: 0.1
---
::

**Properties:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `axis` | `'x' \| 'y'` | required | Which pointer axis drives this prop. |
| `outputMin` | `number` | required | Output value when axis is at 0 (left or top). |
| `outputMax` | `number` | required | Output value when axis is at 1 (right or bottom). |
| `curve` | `number` | `0` | Power curve: `-1` to `+1`. Negative = biased toward `outputMin`, positive = toward `outputMax`. |
| `smoothing` | `number` | `0` | Lag (0-1). Same as `mouse-position`. |
| `momentum` | `number` | `0` | Spring bounce (0-1). Same as `mouse-position`. |

**Use cases:** Blur amount from horizontal position, brightness from vertical position, hue shift, zoom intensity.

## map

Drives a numeric prop from the visual output (alpha channel or luminance) of another named layer. The source layer's rendered pixels become a control signal.

First, give the source component an `id`. Then reference that id in the driver:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid", "JS"]'}
```vue-html
<Shader class="w-full h-64">
  <!-- Source layer: named 'grad' -->
  <LinearGradient
    id="grad"
    color-a="#000000"
    color-b="#ffffff"
  />

  <!-- DotGrid dot size driven by the gradient's luminance -->
  <DotGrid
    :dot-size="{
      type: 'map',
      source: 'grad',
      channel: 'luminance',
      inputMin: 0,
      inputMax: 1,
      outputMin: 0.02,
      outputMax: 0.12
    }"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient
    id="grad"
    colorA="#000000"
    colorB="#ffffff"
  />
  <DotGrid
    dotSize={{
      type: 'map',
      source: 'grad',
      channel: 'luminance',
      inputMin: 0,
      inputMax: 1,
      outputMin: 0.02,
      outputMax: 0.12
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient
    id="grad"
    colorA="#000000"
    colorB="#ffffff"
  />
  <DotGrid
    dotSize={{
      type: 'map',
      source: 'grad',
      channel: 'luminance',
      inputMin: 0,
      inputMax: 1,
      outputMin: 0.02,
      outputMax: 0.12
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient
    id="grad"
    colorA="#000000"
    colorB="#ffffff"
  />
  <DotGrid
    dotSize={{
      type: 'map',
      source: 'grad',
      channel: 'luminance',
      inputMin: 0,
      inputMax: 1,
      outputMin: 0.02,
      outputMax: 0.12
    }}
  />
</Shader>
```

```javascript
const shader = await createShader(canvas, {
  components: [
    {
      type: 'LinearGradient',
      id: 'grad',
      props: { colorA: '#000000', colorB: '#ffffff' }
    },
    {
      type: 'DotGrid',
      props: {
        dotSize: {
          type: 'map',
          source: 'grad',
          channel: 'luminance',
          inputMin: 0,
          inputMax: 1,
          outputMin: 0,
          outputMax: 1
        }
      }
    }
  ]
})
```
::

::shader-demo
---
preset:
  components:
    - type: LinearGradient
      id: grad
      props:
        colorA: "#000000"
        colorB: "#ffffff"
        angle: 45
        visible: false
    - type: DotGrid
      props:
        dotSize:
          type: map
          source: grad
          channel: luminance
          inputMin: 0
          inputMax: 1
          outputMin: 0
          outputMax: 0.8
---
::

**Properties:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `source` | `string` | required | The `id` of the source component to sample from. |
| `channel` | `string` | required | Which channel to extract. `alpha`, `alphaInverted`, `luminance`, `luminanceInverted`. |
| `inputMin` | `number` | required | Source values at or below this are treated as 0. |
| `inputMax` | `number` | required | Source values at or above this are treated as 1. |
| `outputMin` | `number` | required | Output value when input = 0. |
| `outputMax` | `number` | required | Output value when input = 1. |
| `curve` | `number` | `0` | Power curve applied after normalization. `-1` to `+1`. |

The `map` type causes the source layer to be rendered to a texture (RTT) on first use. This is a one-time cost. The source renders independently and its output is sampled each frame.

## Combining dynamic props

Multiple dynamic props can be active on the same component - one per prop:

::code-group{:tabs='["Vue", "React", "Svelte", "Solid"]'}
```vue-html
<Shader class="w-full h-64">
  <LinearGradient color-a="#0f172a" color-b="#4f46e5" />
  <LensFlare
    :center="{ type: 'mouse-position', smoothing: 0.1 }"
    :intensity="{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.4,
      outputMax: 1.0,
      speed: 0.6
    }"
  />
</Shader>
```

```jsx
<Shader className="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#4f46e5" />
  <LensFlare
    center={{ type: 'mouse-position', smoothing: 0.1 }}
    intensity={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.4,
      outputMax: 1.0,
      speed: 0.6
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#4f46e5" />
  <LensFlare
    center={{ type: 'mouse-position', smoothing: 0.1 }}
    intensity={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.4,
      outputMax: 1.0,
      speed: 0.6
    }}
  />
</Shader>
```

```tsx
<Shader class="w-full h-64">
  <LinearGradient colorA="#0f172a" colorB="#4f46e5" />
  <LensFlare
    center={{ type: 'mouse-position', smoothing: 0.1 }}
    intensity={{
      type: 'auto-animate',
      mode: 'ping-pong',
      outputMin: 0.4,
      outputMax: 1.0,
      speed: 0.6
    }}
  />
</Shader>
```
::

## Updating at runtime (JS API)

In the JavaScript API, `shader.update()` also accepts dynamic prop configs - you can switch between a static value and a dynamic prop at any point:

```javascript
// Start with a static value
const shader = await createShader(canvas, {
  components: [
    { type: 'Circle', id: 'c', props: { radius: 0.4 } }
  ]
})

// Later: switch to animated
shader.update('c', {
  radius: {
    type: 'auto-animate',
    mode: 'ping-pong',
    outputMin: 0.2,
    outputMax: 0.7
  }
})

// Later: switch back to static
shader.update('c', { radius: 0.4 })
```
