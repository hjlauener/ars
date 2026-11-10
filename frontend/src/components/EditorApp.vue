<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import Sortable from "sortablejs";
import type { AppConfig, QType, Question } from "../types.ts";
import { postJson, useI18n } from "../util.ts";
import ConfirmDialog from "./ConfirmDialog.vue";
import QuestionForm from "./QuestionForm.vue";
import TypeIcon from "./TypeIcon.vue";
import TypePicker from "./TypePicker.vue";

/**
 * Tab "Fragen": list of the questions (order by dragging or with the
 * buttons) and the form of the selected question. Saves automatically.
 */
const props = defineProps<{ config: AppConfig & { presentUrl?: string } }>();
const { t, tn } = useI18n();
const api = props.config.apiUrl ?? "";

const questions = ref<Question[]>([]);
const selectedId = ref<number | null>(null);
const loaded = ref(false);
const picking = ref(false);
const busy = ref(false);
const status = ref<"idle" | "saving" | "saved" | "error">("idle");
const errorText = ref("");
const deleting = ref<Question | null>(null);
const list = ref<HTMLElement | null>(null);
const pickerDialog = ref<HTMLDialogElement | null>(null);

let sortable: Sortable | null = null;
let saveTimer: number | undefined;
/** changed questions not yet saved (by id); saved one after the other, retried after network errors */
const pending = new Map<number, Question>();
let saving: Promise<void> = Promise.resolve();

const selected = computed(() => questions.value.find((q) => q.id === selectedId.value) ?? null);
const selectedIndex = computed(() => questions.value.findIndex((q) => q.id === selectedId.value));

async function call<T>(body: Record<string, unknown>): Promise<T | null> {
  try {
    const result = await postJson<T & { error?: string }>(api, body);
    if (result.error) {
      errorText.value = result.error;
      return null;
    }
    errorText.value = "";
    return result;
  } catch {
    errorText.value = t("err_connection");
    status.value = "error";
    return null;
  }
}

async function load(): Promise<void> {
  const result = await call<{ questions: Question[] }>({ action: "questions" });
  if (result) {
    questions.value = result.questions;
    selectedId.value = result.questions[0]?.id ?? null;
  }
  loaded.value = true;
  await nextTick();
  initSortable();
}

function initSortable(): void {
  if (!list.value || sortable) {
    return;
  }
  sortable = Sortable.create(list.value, {
    handle: ".uda-qlist__handle",
    animation: 150,
    forceFallback: true,
    fallbackTolerance: 4,
    ghostClass: "is-ghost",
    onEnd: (event) => {
      if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
        return;
      }
      // Sortable moved the DOM node; Vue renders the list again from the data
      const item = list.value?.children[event.newIndex];
      const ref = list.value?.children[event.oldIndex + (event.oldIndex > event.newIndex ? 1 : 0)] ?? null;
      if (item) {
        list.value?.insertBefore(item, ref);
      }
      void move(event.oldIndex, event.newIndex);
    },
  });
}

async function move(from: number, to: number): Promise<void> {
  const copy = [...questions.value];
  const [q] = copy.splice(from, 1);
  copy.splice(to, 0, q);
  questions.value = copy;
  const result = await call<{ questions: Question[] }>({ action: "reorder", ids: copy.map((x) => x.id) });
  if (result) {
    questions.value = result.questions;
  }
}

function openPicker(): void {
  picking.value = true;
  void nextTick(() => pickerDialog.value?.showModal());
}

function closePicker(): void {
  picking.value = false;
  pickerDialog.value?.close();
}

async function add(type: QType): Promise<void> {
  await flush();
  busy.value = true;
  const result = await call<{ id: number; questions: Question[] }>({ action: "add", type });
  busy.value = false;
  closePicker();
  if (result) {
    questions.value = result.questions;
    selectedId.value = result.id;
    await nextTick();
    document.querySelector<HTMLTextAreaElement>(".uda-qform textarea")?.focus();
  }
}

async function select(id: number): Promise<void> {
  await flush();
  selectedId.value = id;
}

function onChange(question: Question): void {
  const i = questions.value.findIndex((q) => q.id === question.id);
  if (i >= 0) {
    questions.value[i] = question;
  }
  pending.set(question.id, question);
  status.value = "saving";
  scheduleSave(700);
}

function scheduleSave(ms: number): void {
  window.clearTimeout(saveTimer);
  saveTimer = window.setTimeout(() => void flush(), ms);
}

/** saves all open changes; resolves when they are stored (or failed) */
function flush(): Promise<void> {
  window.clearTimeout(saveTimer);
  saving = saving.then(async () => {
    for (const [id, question] of [...pending]) {
      pending.delete(id);
      try {
        const result = await postJson<{ error?: string }>(api, { action: "save", question });
        // an error of the server (e.g. question deleted meanwhile) is not repeated
        errorText.value = result.error ?? "";
      } catch {
        if (!pending.has(id)) {
          pending.set(id, question);
        }
        status.value = "error";
        errorText.value = t("err_connection");
        scheduleSave(3000);
        return;
      }
    }
    if (pending.size === 0) {
      status.value = "saved";
    }
  });
  return saving;
}

async function duplicate(): Promise<void> {
  if (!selected.value) {
    return;
  }
  await flush();
  const result = await call<{ id: number; questions: Question[] }>({ action: "duplicate", id: selected.value.id });
  if (result) {
    questions.value = result.questions;
    selectedId.value = result.id;
  }
}

async function confirmDelete(): Promise<void> {
  const question = deleting.value;
  deleting.value = null;
  if (!question) {
    return;
  }
  pending.delete(question.id);
  const index = questions.value.findIndex((q) => q.id === question.id);
  const result = await call<{ questions: Question[] }>({ action: "delete", id: question.id });
  if (result) {
    questions.value = result.questions;
    selectedId.value = result.questions[Math.min(index, result.questions.length - 1)]?.id ?? null;
  }
}

function moveSelected(delta: number): void {
  const i = selectedIndex.value;
  if (i < 0 || i + delta < 0 || i + delta >= questions.value.length) {
    return;
  }
  void move(i, i + delta);
}

/** save what is still open when leaving the page */
function onPageHide(): void {
  for (const question of pending.values()) {
    void fetch(api, {
      method: "POST",
      keepalive: true,
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "save", question }),
    });
  }
  pending.clear();
}

onMounted(() => {
  void load();
  window.addEventListener("pagehide", onPageHide);
});
onBeforeUnmount(() => {
  window.removeEventListener("pagehide", onPageHide);
  sortable?.destroy();
});

const statusText = computed(() =>
  status.value === "saving" ? t("editor_saving") : status.value === "saved" ? t("editor_saved") : status.value === "error" ? t("editor_save_error") : "",
);
</script>

<template>
  <div class="uda-editor">
    <div class="uda-toolbar">
      <h2 class="uda-toolbar__title">{{ tn("editor_n_questions", questions.length) }}</h2>
      <span class="uda-status" :class="`is-${status}`" role="status">{{ statusText }}</span>
      <span class="uda-toolbar__spacer"></span>
      <button type="button" class="uda-btn" @click="openPicker">+ {{ t("editor_add_question") }}</button>
      <a v-if="config.presentUrl && questions.length" class="uda-btn uda-btn--primary" :href="config.presentUrl">{{ t("editor_go_present") }}</a>
    </div>

    <p v-if="errorText" class="uda-alert" role="alert">{{ errorText }}</p>

    <div v-if="loaded && questions.length === 0" class="uda-panel">
      <TypePicker :types="config.types ?? []" :busy="busy" @choose="add" />
    </div>

    <div v-show="questions.length > 0" class="uda-editor__layout">
      <nav class="uda-qlist-wrap" :aria-label="t('editor_list_label')">
        <ol ref="list" class="uda-qlist">
          <li v-for="(q, i) in questions" :key="q.id" class="uda-qlist__item" :class="{ 'is-active': q.id === selectedId }">
            <span class="uda-qlist__handle" :title="t('editor_drag')" aria-hidden="true">⋮⋮</span>
            <button type="button" class="uda-qlist__btn" :aria-current="q.id === selectedId ? 'true' : undefined" @click="select(q.id)">
              <span class="uda-qlist__nr">{{ i + 1 }}</span>
              <TypeIcon :type="q.type" :size="18" />
              <span class="uda-qlist__title">{{ q.title.trim() || t("editor_untitled") }}</span>
            </button>
          </li>
        </ol>
        <div v-if="selected" class="uda-qlist__move">
          <button type="button" class="uda-btn uda-btn--small" :disabled="selectedIndex <= 0" @click="moveSelected(-1)">↑ {{ t("move_up") }}</button>
          <button type="button" class="uda-btn uda-btn--small" :disabled="selectedIndex >= questions.length - 1" @click="moveSelected(1)">↓ {{ t("move_down") }}</button>
        </div>
      </nav>

      <section class="uda-panel uda-editor__form">
        <QuestionForm
          v-if="selected"
          :key="selected.id"
          :question="selected"
          :number="selectedIndex + 1"
          :quiz-limits="config.quizLimits ?? [0]"
          @change="onChange"
          @duplicate="duplicate"
          @remove="deleting = selected"
        />
      </section>
    </div>

    <dialog v-if="picking" ref="pickerDialog" class="uda-dialog uda-dialog--wide" @cancel.prevent="closePicker" @close="picking = false">
      <button type="button" class="uda-dialog__close" :aria-label="t('close')" @click="closePicker">×</button>
      <TypePicker :types="config.types ?? []" :busy="busy" @choose="add" />
    </dialog>

    <ConfirmDialog
      :open="deleting !== null"
      :title="t('editor_delete_title')"
      :text="t('editor_delete_text', deleting?.title.trim() || t('editor_untitled'))"
      :confirm="t('editor_delete')"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
