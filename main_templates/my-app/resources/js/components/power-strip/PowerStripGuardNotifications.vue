<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Toaster } from '@/components/ui/sonner'

type AppToastNotification = {
  level?: 'success' | 'info' | 'warning' | 'error'
  title?: string
  message?: string
  detail?: string | null
}

type DeleteDialogPayload = {
  title?: string
  actionUrl?: string
  policySummary?: string
}

declare global {
  interface Window {
    pulsenodeShowPowerStripToast?: (notification: AppToastNotification) => void
    pulsenodeOpenPowerStripDeleteDialog?: (payload: DeleteDialogPayload) => void
  }
}

const toastOptions = {
  duration: 4200,
  closeButton: false,
  class: 'w-[22rem] rounded-2xl border border-border/50 bg-[rgba(18,18,18,0.96)] shadow-2xl backdrop-blur-md',
  descriptionClass: 'text-[11px] leading-4 text-muted-foreground',
  classes: {
    title: 'text-xs font-semibold',
    description: 'text-[11px] leading-4 text-muted-foreground',
    actionButton: '!h-7 !rounded-lg !border !px-2.5 !text-[11px] !font-medium',
    success: 'border-emerald-500/45 bg-[rgba(9,34,24,0.97)] text-emerald-50',
    error: 'border-red-500/45 bg-[rgba(42,10,10,0.97)] text-red-50',
    warning: 'border-amber-500/45 bg-[rgba(34,24,10,0.97)] text-amber-50',
    info: 'border-sky-500/45 bg-[rgba(12,20,30,0.97)] text-sky-50',
  },
}

const deleteDialogOpen = ref(false)
const deleteTarget = ref<DeleteDialogPayload | null>(null)

function showToast(notification: AppToastNotification): void {
  const level = notification.level || 'info'
  const title = notification.title || notification.message || 'Notification'
  const description =
    notification.title && notification.message
      ? notification.message
      : notification.detail ?? undefined

  toast.dismiss('power-strip-feedback')

  const toastFn =
    level === 'success'
      ? toast.success
      : level === 'error'
        ? toast.error
        : level === 'warning'
          ? toast.warning
          : toast.info

  toastFn(title, {
    id: 'power-strip-feedback',
    description,
    action: {
      label: 'Close',
      onClick: () => toast.dismiss('power-strip-feedback'),
    },
  })
}

function openDeleteDialog(payload: DeleteDialogPayload): void {
  deleteTarget.value = payload
  deleteDialogOpen.value = Boolean(payload.actionUrl)
}

function submitDelete(): void {
  if (!deleteTarget.value?.actionUrl) return

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = deleteTarget.value.actionUrl
  form.style.display = 'none'

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const tokenInput = document.createElement('input')
  tokenInput.type = 'hidden'
  tokenInput.name = '_token'
  tokenInput.value = csrf
  form.appendChild(tokenInput)

  const methodInput = document.createElement('input')
  methodInput.type = 'hidden'
  methodInput.name = '_method'
  methodInput.value = 'DELETE'
  form.appendChild(methodInput)

  const summaryInput = document.createElement('input')
  summaryInput.type = 'hidden'
  summaryInput.name = 'policy_summary'
  summaryInput.value = deleteTarget.value.policySummary || ''
  form.appendChild(summaryInput)

  document.body.appendChild(form)
  form.submit()
}

onMounted(() => {
  window.pulsenodeShowPowerStripToast = (notification: AppToastNotification) => {
    showToast(notification)
  }
  window.pulsenodeOpenPowerStripDeleteDialog = openDeleteDialog
})

onBeforeUnmount(() => {
  if (window.pulsenodeShowPowerStripToast) {
    delete window.pulsenodeShowPowerStripToast
  }
  if (window.pulsenodeOpenPowerStripDeleteDialog) {
    delete window.pulsenodeOpenPowerStripDeleteDialog
  }
})
</script>

<template>
  <Toaster
    position="top-right"
    :expand="false"
    :visible-toasts="1"
    :toast-options="toastOptions"
    container-aria-label="Power Strip notifications"
  />

  <Dialog v-model:open="deleteDialogOpen">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader class="space-y-2">
        <DialogTitle>Delete guard policy?</DialogTitle>
        <DialogDescription>
          This will remove the selected guard policy and it cannot be restored.
        </DialogDescription>
      </DialogHeader>

      <div class="rounded-2xl border border-border/30 bg-background/70 p-4 text-sm leading-6 text-muted-foreground">
        <p class="font-medium text-foreground">
          {{ deleteTarget?.title || 'Selected policy' }}
        </p>
        <p class="mt-1 break-words">
          {{ deleteTarget?.policySummary || 'No policy summary available' }}
        </p>
        <p class="mt-3">
          If you continue, the policy will be removed from MongoDB and from the live list.
        </p>
      </div>

      <DialogFooter class="gap-2">
        <DialogClose as-child>
          <Button variant="secondary">
            Cancel
          </Button>
        </DialogClose>
        <Button
          type="button"
          variant="destructive"
          :disabled="deleteTarget === null || !deleteTarget.actionUrl"
          @click="submitDelete"
        >
          Delete policy
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
