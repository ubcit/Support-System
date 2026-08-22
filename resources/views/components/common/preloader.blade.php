{{--
    Full-screen "first paint" preloader.

    This must only ever appear once per real browser page load — never on
    wire:navigate transitions, which morph the <body> (re-mounting this
    component) without firing `DOMContentLoaded` again. The previous version
    relied on that event + a parent-scoped `loaded` variable, so after the
    first wire:navigate click it got stuck fully visible forever (the "bad
    loading circle").

    Fix: track "has this browser tab already booted" on `window` itself,
    which survives wire:navigate's SPA-style body morphing and only resets
    on an actual full page reload.
--}}
<div
    x-data="{ show: !window.__appBooted }"
    x-init="window.__appBooted ? (show = false) : setTimeout(() => { window.__appBooted = true; show = false }, 300)"
    x-show="show"
    class="fixed left-0 top-0 z-999999 flex h-screen w-screen items-center justify-center bg-white dark:bg-black"
>
  <div
    class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"
  ></div>
</div>
