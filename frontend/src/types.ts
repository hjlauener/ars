export type QType = "choice" | "cloud" | "rank" | "quiz";

export const ALL_TYPES: QType[] = ["choice", "cloud", "rank", "quiz"];

export interface Answer {
  id: string;
  text: string;
  correct?: boolean;
}

export interface QuestionSettings {
  multiple?: boolean;
  maxWords?: number;
  timeLimit?: number;
  fullOrder?: boolean;
}

export interface Question {
  id: number;
  type: QType;
  title: string;
  answers: Answer[];
  settings: QuestionSettings;
}

export interface OptionResult {
  id: string;
  text: string;
  count: number;
  correct?: boolean;
}

export interface RankResult {
  id: string;
  text: string;
  avgRank: number | null;
  score: number;
}

export interface LeaderEntry {
  name: string;
  points: number;
  me?: boolean;
}

export interface Results {
  type: QType;
  total: number;
  options?: OptionResult[];
  words?: { text: string; count: number }[];
  distinct?: number;
  items?: RankResult[];
  leaderboard?: LeaderEntry[];
}

/** configuration of the views inside ILIAS (data-config of .uda-app) */
export interface AppConfig {
  view: "editor" | "presenter" | "results" | "join";
  title: string;
  code: string;
  joinUrl: string;
  displayUrl: string;
  palette: string[];
  resultMode: "auto" | "release";
  lang: string;
  texts: Record<string, string>;
  apiUrl?: string;
  exportUrl?: string;
  types?: QType[];
  quizLimits?: number[];
  quizPoints?: number;
  quizBonus?: number;
}

/** configuration of the participation page (join.php) */
export interface JoinConfig {
  apiUrl: string;
  /** static signal file of the presentation ("" if there is none) */
  signalUrl: string;
  code: string;
  lang: string;
  palette: string[];
  loggedIn: boolean;
  quizPoints: number;
  texts: Record<string, string>;
}

export interface PresenterSession {
  id: number;
  started: number;
  questionId: number;
  voting: boolean;
  showResults: boolean;
  rev: number;
}

export interface PresenterState {
  questions: Question[];
  session: PresenterSession | null;
  participants?: { total: number; active: number };
  remainingMs?: number | null;
  results?: Results;
  error?: string;
}

export interface SessionInfo {
  id: number;
  started: number;
  ended: number;
  participants: number;
  responses: number;
}

export interface ParticipantState {
  status: "waiting" | "ended" | "running" | "login" | "denied" | "unknown";
  title?: string;
  rev?: number;
  hasQuiz?: boolean;
  name?: string;
  loginUrl?: string;
  question?: Question;
  position?: { index: number; count: number };
  voting?: boolean;
  remainingMs?: number | null;
  myAnswer?: string[] | null;
  showResults?: boolean;
  results?: Results;
  quiz?: { correct: string[]; points: number; total: number; rank: number | null; of: number };
  error?: string;
}
