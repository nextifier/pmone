---
title: What is Shaders?
description: GPU-accelerated visual effects for Vue, React, Svelte, Solid & JS
icon: hand-wave
category: concepts
---

# What is Shaders?

Shaders is a component library for building GPU-accelerated visual effects directly in the browser. You describe what you want using the same declarative, component-based syntax you already know from building your frontend UI - and Shaders handles all the GPU-side work for you.

::shader-demo
---
preset:
  components:
    - type: StudioBackground
      props:
        ambientIntensity: 100
        ambientSpeed: 1.9
        backIntensity: 32
        center:
          x: 0.49
          y: 0.59
        color: "#1a1b2b"
        lightTarget: 28
        wallCurvature: 14
    - type: ChromaFlow
      props:
        downColor: "#f256f5"
        intensity: 1.3
        momentum: 34
        radius: 3.8
        upColor: "#ffaa00"
        visible: false
    - type: Fog
      props:
        blending: 0.65
        colorA: "#193e9e"
        colorB: "#000000"
        detail: 34
        mouseInfluence: 0.65
        opacity: 0.14
        seed: 405
        speed: 0.3
        turbulence: 3
    - type: Glass
      props:
        aberration: 0
        cutout: true
        fresnel: 0.08
        fresnelSoftness: 0.06
        highlight: 0.9
        highlightSoftness: 0.6
        lightAngle: 264
        refraction: 1.07
        shapeSdfUrl: "https://data.shaders.com/storage/v1/object/public/user-uploaded-images/user_33nh0FG48zZa0rIUZuK7vgwPfZe/gF3wGRmpQiqP_sdf.bin"
        thickness: 0.05
      children:
        - type: ColorWheel
          props:
            angle: 57
            colorA: "#6a00ff"
            colorB: "#3700ff"
            colorC: "#ffaa00"
            colorSpace: "lch"
            mode: "custom"
            scale: 10
            speed: 0.3
        - type: Halftone
          props:
            frequency: 151
            opacity: 0.17
            style: "cmyk"
    - type: ReflectivePlane
      props:
        blur: 3.05
        blurDistance: 0.11
        distance: 0.19
        falloff: 1.88
        height: 0.59
        opacity: 0.1
---
::

## How does it work?

You define components and props within a `<Shader>` tag, and we compile that into a WebGPU shader program, and handle any blending/masking/etc. to output the final look.

It all renders to a single `<canvas>` element. You size and position it with CSS, just like any other HTML element. No matter how many nested layers you define, the result is always a single visual element.

```vue-html
<Shader class="absolute inset-0 -z-10">
  <LinearGradient colorA="#0f172a" colorB="#7c3aed" />
  <CursorTrail />
</Shader>
```

There's no crazy math to learn, no GLSL to write, and no render loop to manage. Changes to prop values update instantly without recompiling the shader, keeping things lightweight for the browser.

## What can you build?

You can build a TON of different effects with Shaders, ranging from hero section backgrounds to interactive and stylized media. Every component comes with configurable props that, when combined, can create a wide range of different interesting visuals. Power features like masking, blending, and dynamic props provide even more ways to build something that's truly unique.

## Supported frameworks

Shaders ships a single package with dedicated entry points for each framework. Component-based frameworks (Vue, React, Svelte, Solid) share the same declarative API. The JavaScript API uses a preset config approach better suited to imperative workflows.

## Requirements

Shaders uses **WebGPU**, which is supported in most modern browsers now. When WebGPU is unavailable, we fallback gracefully to WebGLv2.