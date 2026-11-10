<script setup lang="ts">
import { nextTick, ref, watch } from "vue";
import { useI18n } from "../util.ts";

/**
 * Confirmation as overlay (like the interruptive modal of ILIAS): native
 * <dialog>, closes with Escape, focus on "Abbrechen".
 */
const props = defineProps<{ open: boolean; title: string; text: string; confirm: string }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const { t } = useI18n();
const dialog = ref<HTMLDialogElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);

watch(
  () => props.open,
  async (open) => {
    await nextTick();
    if (!dialog.value) {
      return;
    }
    if (open && !dialog.value.open) {
      dialog.value.showModal();
      cancelButton.value?.focus();
    } else if (!open && dialog.value.open) {
      dialog.value.close();
    }
  },
  { immediate: true },
);
</script>

<template>
  <dialog ref="dialog" class="uda-dialog" @cancel.prevent="emit('cancel')">
    <h2 class="uda-dialog__title">{{ title }}</h2>
    <p class="uda-dialog__text">{{ text }}</p>
    <div class="uda-dialog__actions">
      <button ref="cancelButton" type="button" class="uda-btn" @click="emit('cancel')">{{ t("cancel") }}</button>
      <button type="button" class="uda-btn uda-btn--danger" @click="emit('confirm')">{{ confirm }}</button>
    </div>
  </dialog>
</template>
