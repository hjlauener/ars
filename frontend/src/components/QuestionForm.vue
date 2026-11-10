<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import type { Answer, Question } from "../types.ts";
import { useI18n } from "../util.ts";
import TypeIcon from "./TypeIcon.vue";

/**
 * Form of one question. Every change is passed on at once (the editor
 * saves automatically). Answers get their id here, so that the server
 * keeps them.
 */
const props = defineProps<{ question: Question; quizLimits: number[]; number: number }>();
const emit = defineEmits<{ change: [question: Question]; duplicate: []; remove: [] }>();
const { t, tn } = useI18n();

/** deep copy (also of reactive objects, which structuredClone refuses) */
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

const MAX_ANSWERS: Record<string, number> = { choice: 10, rank: 10, quiz: 6, cloud: 0 };

const q = ref<Question>(clone(props.question));
const answerInputs = ref<HTMLInputElement[]>([]);

watch(
  () => props.question.id,
  () => {
    q.value = clone(props.question);
  },
);

const hasAnswers = computed(() => q.value.type !== "cloud");
const filled = computed(() => q.value.answers.filter((a) => a.text.trim() !== "").length);
const canAdd = computed(() => q.value.answers.length < MAX_ANSWERS[q.value.type]);
const warnings = computed(() => {
  const list: string[] = [];
  if (q.value.title.trim() === "") {
    list.push(t("editor_warn_title"));
  }
  if (hasAnswers.value && filled.value < 2) {
    list.push(t("editor_warn_answers"));
  }
  if (q.value.type === "quiz" && !q.value.answers.some((a) => a.correct && a.text.trim() !== "")) {
    list.push(t("editor_warn_correct"));
  }
  return list;
});

function changed(): void {
  emit("change", clone(q.value));
}

function newId(): string {
  const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
  let id = "";
  const bytes = new Uint8Array(8);
  crypto.getRandomValues(bytes);
  for (const b of bytes) {
    id += chars[b % chars.length];
  }
  return id;
}

async function addAnswer(after?: number): Promise<void> {
  if (!canAdd.value) {
    return;
  }
  const answer: Answer = { id: newId(), text: "", correct: false };
  const at = after === undefined ? q.value.answers.length : after + 1;
  q.value.answers.splice(at, 0, answer);
  changed();
  await nextTick();
  answerInputs.value[at]?.focus();
}

function removeAnswer(i: number): void {
  q.value.answers.splice(i, 1);
  changed();
}

function setCorrect(i: number): void {
  q.value.answers.forEach((a, j) => (a.correct = i === j));
  changed();
}

function moveAnswer(i: number, delta: number): void {
  const j = i + delta;
  if (j < 0 || j >= q.value.answers.length) {
    return;
  }
  const [a] = q.value.answers.splice(i, 1);
  q.value.answers.splice(j, 0, a);
  changed();
}

const limitLabel = (s: number): string => (s === 0 ? t("editor_no_limit") : tn("seconds", s));
</script>

<template>
  <form class="uda-qform" @submit.prevent>
    <div class="uda-qform__head">
      <span class="uda-chip">
        <TypeIcon :type="q.type" :size="18" />
        {{ t(`type_${q.type}`) }}
      </span>
      <span class="uda-qform__nr">{{ t("editor_question_nr", number) }}</span>
      <span class="uda-qform__spacer"></span>
      <button type="button" class="uda-btn uda-btn--ghost uda-btn--small" @click="emit('duplicate')">{{ t("editor_duplicate") }}</button>
      <button type="button" class="uda-btn uda-btn--ghost uda-btn--small uda-btn--danger-text" @click="emit('remove')">{{ t("editor_delete") }}</button>
    </div>

    <label class="uda-field">
      <span class="uda-field__label">{{ t("editor_question") }}</span>
      <textarea
        v-model="q.title"
        class="uda-input uda-input--title"
        rows="2"
        maxlength="300"
        :placeholder="t('editor_question_placeholder')"
        @input="changed"
      ></textarea>
    </label>

    <fieldset v-if="hasAnswers" class="uda-field uda-answers">
      <legend class="uda-field__label">{{ t(q.type === "rank" ? "editor_items" : "editor_answers") }}</legend>
      <p class="uda-field__help">{{ t(`editor_help_${q.type}`) }}</p>
      <ol class="uda-answers__list">
        <li v-for="(a, i) in q.answers" :key="a.id" class="uda-answers__row" :class="{ 'is-correct': a.correct }">
          <span class="uda-answers__nr" aria-hidden="true">{{ q.type === "rank" ? i + 1 : String.fromCharCode(65 + i) }}</span>
          <input
            :ref="(el) => { if (el) answerInputs[i] = el as HTMLInputElement; }"
            v-model="a.text"
            class="uda-input"
            type="text"
            maxlength="150"
            :aria-label="t('editor_answer_n', i + 1)"
            :placeholder="t('editor_answer_placeholder', i + 1)"
            @input="changed"
            @keydown.enter.prevent="addAnswer(i)"
          />
          <label v-if="q.type === 'quiz'" class="uda-answers__correct" :title="t('editor_mark_correct')">
            <input type="radio" :name="`correct-${q.id}`" :checked="a.correct" @change="setCorrect(i)" />
            <span>{{ t("editor_correct") }}</span>
          </label>
          <span class="uda-answers__tools">
            <button type="button" class="uda-icon-btn" :disabled="i === 0" :aria-label="t('move_up')" @click="moveAnswer(i, -1)">↑</button>
            <button type="button" class="uda-icon-btn" :disabled="i === q.answers.length - 1" :aria-label="t('move_down')" @click="moveAnswer(i, 1)">↓</button>
            <button type="button" class="uda-icon-btn" :disabled="q.answers.length <= 2" :aria-label="t('editor_remove_answer')" @click="removeAnswer(i)">×</button>
          </span>
        </li>
      </ol>
      <button v-if="canAdd" type="button" class="uda-btn uda-btn--small" @click="addAnswer()">+ {{ t(q.type === "rank" ? "editor_add_item" : "editor_add_answer") }}</button>
    </fieldset>

    <div class="uda-field uda-settings">
      <span class="uda-field__label">{{ t("editor_settings") }}</span>
      <label v-if="q.type === 'choice'" class="uda-check">
        <input v-model="q.settings.multiple" type="checkbox" @change="changed" />
        <span>{{ t("editor_multiple") }}</span>
      </label>
      <label v-if="q.type === 'cloud'" class="uda-inline">
        <span>{{ t("editor_max_words") }}</span>
        <select v-model.number="q.settings.maxWords" class="uda-input uda-input--auto" @change="changed">
          <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
        </select>
      </label>
      <template v-if="q.type === 'quiz'">
        <label class="uda-inline">
          <span>{{ t("editor_time_limit") }}</span>
          <select v-model.number="q.settings.timeLimit" class="uda-input uda-input--auto" @change="changed">
            <option v-for="s in quizLimits" :key="s" :value="s">{{ limitLabel(s) }}</option>
          </select>
        </label>
        <p class="uda-field__help">{{ t("editor_quiz_points") }}</p>
      </template>
      <p v-if="q.type === 'rank'" class="uda-field__help">{{ t("editor_rank_info") }}</p>
    </div>

    <ul v-if="warnings.length" class="uda-warnings">
      <li v-for="w in warnings" :key="w">{{ w }}</li>
    </ul>
  </form>
</template>
