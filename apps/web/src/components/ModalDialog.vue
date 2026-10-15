<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps<{ open: boolean; title: string }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()
const dialog = ref<HTMLDialogElement | null>(null)

function syncOpen() {
  if (!dialog.value) return
  if (props.open && !dialog.value.open) dialog.value.showModal()
  else if (!props.open && dialog.value.open) dialog.value.close()
}

watch(() => props.open, async () => {
  await nextTick()
  syncOpen()
}, { flush: 'post' })

onMounted(syncOpen)

function close() {
  emit('update:open', false)
}

function onClose() {
  if (props.open) close()
}

function onBackdropClick(event: MouseEvent) {
  if (event.target === dialog.value) close()
}
</script>

<template>
  <dialog ref="dialog" class="admin-modal" :aria-label="title" @cancel.prevent="close" @close="onClose" @click="onBackdropClick">
    <div class="admin-modal-panel">
      <header class="admin-modal-header">
        <div><p class="eyebrow">ADMIN WORKSPACE</p><h2>{{ title }}</h2></div>
        <button class="modal-close" type="button" aria-label="Close dialog" @click="close">×</button>
      </header>
      <div class="admin-modal-body"><slot /></div>
    </div>
  </dialog>
</template>
