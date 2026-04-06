<script setup lang="ts">
import type { ContextMenuSubContentEmits, ContextMenuSubContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { nextTick, onMounted, onUnmounted, ref } from "vue"
import {
  ContextMenuSubContent,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ContextMenuSubContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ContextMenuSubContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const contentRef = ref<unknown>(null)

function resolveElement(target: unknown): HTMLElement | null {
  if (target instanceof HTMLElement)
    return target

  if (
    target
    && typeof target === "object"
    && "$el" in target
    && target.$el instanceof HTMLElement
  ) {
    return target.$el
  }

  return null
}

function applyAutoSide() {
  const element = resolveElement(contentRef.value)

  if (!element || typeof window === "undefined")
    return

  const triggerId = element.getAttribute("aria-labelledby")
  const trigger = triggerId ? document.getElementById(triggerId) : null

  if (!trigger)
    return

  const elementRect = element.getBoundingClientRect()
  const triggerRect = trigger.getBoundingClientRect()
  const gap = 10
  const viewportPadding = 8
  const spaceRight = window.innerWidth - triggerRect.right
  const spaceLeft = triggerRect.left
  const shouldOpenRight = spaceRight >= elementRect.width + gap || spaceRight >= spaceLeft
  const desiredLeft = shouldOpenRight
    ? triggerRect.right + gap
    : triggerRect.left - elementRect.width - gap
  const clampedLeft = Math.min(
    Math.max(viewportPadding, desiredLeft),
    Math.max(viewportPadding, window.innerWidth - elementRect.width - viewportPadding),
  )

  element.style.left = `${clampedLeft}px`
  element.dataset.autoSide = shouldOpenRight ? "right" : "left"
  element.style.setProperty(
    "--reka-context-menu-content-transform-origin",
    shouldOpenRight ? "left top" : "right top",
  )
}

function queueAutoSide() {
  nextTick(() => {
    requestAnimationFrame(() => {
      applyAutoSide()
    })
  })
}

function handleWindowChange() {
  queueAutoSide()
}

onMounted(() => {
  queueAutoSide()
  window.addEventListener("resize", handleWindowChange)
  window.addEventListener("scroll", handleWindowChange, true)
})

onUnmounted(() => {
  window.removeEventListener("resize", handleWindowChange)
  window.removeEventListener("scroll", handleWindowChange, true)
})
</script>

<template>
  <ContextMenuSubContent
    ref="contentRef"
    data-slot="context-menu-sub-content"
    v-bind="forwarded"
    :class="cn('bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 min-w-[8rem] origin-(--reka-context-menu-content-transform-origin) overflow-hidden rounded-md border p-1 shadow-lg', props.class)"
  >
    <slot />
  </ContextMenuSubContent>
</template>
