# Udon ARS – Technik

Technische Beschreibung für alle, die am Plugin weiterarbeiten (Menschen und KIs).
Bei jeder Änderung an Aufbau, Bibliotheken, Datenbank oder Schnittstellen nachführen.

## Grundidee

Ein schlankes Audience-Response-System (ARS) als ILIAS-11-Repository-Objekt
(Typ `xuda`). Ein Udon ARS ist eine **Präsentation**: eine Folge von Fragen.
Die Lehrperson startet eine **Durchführung** (Session), zeigt Frage um Frage
auf dem Beamer, die Teilnehmenden antworten auf dem Smartphone über einen
festen sechsstelligen **Code** oder den **QR-Code**.

Bewusst einfach gehalten: wenige Einstellungen, keine Teilen-Dialoge, keine
eigene Rechteverwaltung – wer was darf, regeln die ILIAS-Rechte.

## Fragetypen

| Typ | Schlüssel | Antwort (gespeichert) | Auswertung |
|---|---|---|---|
| Auswahlfrage | `choice` | Liste von Antwort-IDs (eine oder mehrere) | Anteil der antwortenden Personen je Antwort |
| Wortwolke | `cloud` | Liste von Begriffen (1–10, Standard 3) | Häufigkeit; Gross-/Kleinschreibung zusammengefasst, häufigste Schreibweise wird gezeigt; höchstens 60 Begriffe |
| Rangfolge | `rank` | alle Antwort-IDs in der gewählten Reihenfolge | durchschnittlicher Platz; Wert 0–1 (immer erster = 1) |
| Quiz | `quiz` | eine Antwort-ID | richtige Antwort 100 Punkte, mit Zeitlimit bis 100 Punkte Bonus (linear nach Restzeit); Rangliste der Durchführung |

Antwort-IDs sind kurze Zufallswerte (`[a-z0-9]{1,12}`), damit Antworten beim
Bearbeiten der Frage (Umstellen, Text ändern) gültig bleiben.

## Ablauf einer Durchführung

- **Starten**: neue Session mit der ersten Frage, Abstimmung offen.
- **Frage wechseln**: Abstimmung öffnet sich; Ergebnisse je nach Modus
  sofort sichtbar (`auto`) oder verborgen (`release`). Quiz: Ergebnisse immer
  erst nach dem Ende der Abstimmung (bei `auto` automatisch).
- **Quiz-Zeitlimit**: Startzeit `q_started` (ms) in der Session. Ist die Zeit
  um (+1,5 s Toleranz), schliesst der nächste Abruf (Präsentierende oder
  Teilnehmende) die Abstimmung. Die Restzeit rechnet der Server, die Geräte
  zählen nur lokal herunter (keine Uhrzeit-Unterschiede).
- **Antworten**: eine Antwort je Person und Frage, änderbar solange offen
  (Quiz: nicht änderbar).
- **Beenden**: `ended` gesetzt; die Teilnahme-Seite zeigt 4 Stunden lang
  „Vielen Dank“, danach wieder „Gleich geht es los“.

## Aufbau

```
join.php                       öffentliche Teilnahme-Seite (startet ILIAS ohne Login-Zwang)
classes/
  class.ilObjUdonArs*.php      ILIAS-Objekt, GUI (Reiter, JSON-Schnittstelle "api", CSV), Liste, Zugriff
  class.ilUdonArsPlugin.php    Plugin; Deinstallation löscht Tabellen und Einstellungen
  class.ilUdonArsConfigGUI.php Plugin-Konfiguration
  class.ilUdaConfig.php        globale Einstellungen (ilSetting "xuda"), alle mit Standardwert
  class.ilUdaPalettes.php      die vier Farbpaletten
  class.ilUdaRepository.php    Datenbank
  class.ilUdaService.php       Logik: Fragen prüfen, Sessions, Antworten, Punkte, Auswertung
  class.ilUdaJoinEndpoint.php  Seite und JSON-Schnittstelle der Teilnehmenden
  class.ilUdaTexts.php         Sprachtexte "ui_*" für das Frontend
frontend/                      Vue 3 + TypeScript, gebaut mit Vite nach js/dist/
  src/ars.ts                   Ansichten in ILIAS (Editor, Präsentieren, Ergebnisse, Teilnehmen-Info)
  src/join.ts                  Teilnahme-Seite
  src/components/, src/join/   Komponenten; src/styles/ars.css ein Stylesheet für alles
js/dist/                       gebautes Frontend (wird committet)
```

### Schnittstellen

**Präsentierende** (`ilObjUdonArsGUI::api`, ilCtrl, Recht `write`), POST JSON
`{action, …}`: `questions`, `add`, `duplicate`, `save`, `delete`, `reorder`,
`present`, `start`, `goto`, `voting`, `results`, `reset`, `end`, `sessions`,
`sessionResults`, `deleteSession`. CSV: Befehl `exportCsv&session_id=…` (GET).

**Teilnehmende** (`join.php?a=api`), POST JSON `{action, code, token, …}`:
`state`, `answer` (`questionId`, `answer`), `name` (`name`). Antwort: Zustand
des Geräts (Status, aktuelle Frage ohne richtige Antwort, eigene Antwort,
Restzeit, Ergebnisse falls freigegeben, Quiz-Auflösung).

Beide nehmen nur `Content-Type: application/json` an (eine fremde Seite kann
das nicht per Formular schicken).

### Öffentliche Teilnahme (`join.php`)

`join.php` liegt im Plugin-Ordner (unter `public/Customizing/…`, also direkt
vom Webserver erreichbar), sucht `ilias.ini.php` nach oben, startet ILIAS im
Kontext `ilContext::CONTEXT_WAC` (wie `public/gs_content.php`): keine
Weiterleitung zur Anmeldung, aber Benutzer und Datenbank sind da.
Angemeldete werden an ihrer ILIAS-Sitzung erkannt (Cookie mit Pfad `/`),
alle anderen sind der anonyme Benutzer.

- Teilnahme ohne Anmeldung erlaubt (Standard): Code genügt.
- Ausgeschaltet: anonym → Status `login` mit Link `goto.php?target=xuda_<ref>`;
  angemeldet ohne Leserecht → `denied`.

**Kennung der Teilnehmenden**: anonym ein zufälliges Token je Gerät
(`localStorage`), angemeldet `user:<id>` (eine Stimme pro Konto, auch mit
mehreren Geräten). In der Datenbank steht nur
`sha256(salt | token)`; das Salz wird beim ersten Gebrauch zufällig erzeugt
(ilSetting `xuda/salt`). Keine Benutzer-IDs in den Antworten. Der Name für die
Quiz-Rangliste ist freiwillig.

### Echtzeit: Polling

Kein Mercure/SSE/WebSocket: ILIAS Slim läuft mit Apache/PHP; offene
Verbindungen würden je einen PHP-Prozess belegen, und ein eigener
Echtzeitdienst wäre zusätzlicher Betrieb. Stattdessen fragen die Geräte ab:

- Präsentierende: jede Sekunde (`present`)
- Teilnehmende: alle 1,5 Sekunden (`state`)
- im Hintergrund (Tab nicht sichtbar) fünfmal langsamer, nach Fehlern mit
  wachsender Pause (`Poller` in `frontend/src/util.ts`)

Eine Abfrage von `join.php` dauert lokal etwa 30 ms (ILIAS-Start inklusive).
An die Geräte gehen nur zusammengefasste Ergebnisse, nie einzelne Antworten.
Für sehr grosse Gruppen (über ~300 Personen gleichzeitig) wäre der nächste
Schritt ein kurzer Zwischenspeicher der Ergebnisse je Frage und Sekunde.

## Datenbank

| Tabelle | Inhalt |
|---|---|
| `rep_robj_xuda_data` | je Objekt: `join_code` (6 Ziffern, eindeutig), `palette`, `result_mode` (`auto`/`release`) |
| `rep_robj_xuda_quest` | Fragen: `obj_id`, `sort_nr`, `qtype`, `title`, `answers_json` (`[{id,text,correct}]`), `settings_json` |
| `rep_robj_xuda_sess` | Durchführungen: `started`, `ended` (0 = läuft), `quest_id`, `voting`, `show_results`, `q_started` (ms), `rev` |
| `rep_robj_xuda_part` | Teilnehmende je Session: `token` (Hash), `nickname`, `joined`, `last_seen` |
| `rep_robj_xuda_resp` | Antworten: `session_id`, `quest_id`, `token`, `answer_json`, `points`, `answer_ms`, `created` |

`settings_json` ist immer ein JSON-Objekt (Rangfolge: `{"fullOrder":true}`),
damit es im Frontend nicht als leeres Array ankommt.

„Ergebnisse automatisch löschen nach N Tagen“ betrifft nur beendete
Sessions; gelöscht wird beim Starten einer Präsentation und beim Öffnen des
Reiters „Ergebnisse“ (kein Cron-Job nötig).

Kopieren eines Objekts übernimmt Palette, Ergebnismodus und Fragen, nicht die
Durchführungen; die Kopie erhält einen neuen Code.

## Frontend

| Bibliothek | Version | Lizenz | Wozu |
|---|---|---|---|
| Vue | 3.5.45 | MIT | Oberfläche |
| SortableJS | 1.15.7 | MIT | Ziehen und Ablegen (Rangfolge, Reihenfolge der Fragen), `forceFallback` für Touch |
| qrcode-generator | 2.0.4 | MIT | QR-Code als SVG im Browser |
| Vite | 8.3.7 | MIT | Build |
| TypeScript / vue-tsc | 6.0.3 / 3.3.14 | Apache-2.0 / MIT | Typprüfung (vue-tsc braucht die JavaScript-Version von TypeScript, darum 6.x) |

Bewusst **ohne** Pinia (der Zustand je Ansicht ist klein; `ref`/`computed`
genügen) und **ohne** ECharts: Balken und Rangfolgen sind HTML/CSS
(zugänglich, mit den Paletten eingefärbt, klein), die Wortwolke ist eine
eigene Spiral-Anordnung in `WordCloud.vue` (echter Text, für Screenreader
zusätzlich als Liste).

Grösse (gzip): Teilnahme-Seite etwa 60 KB inkl. Vue, Stylesheet 6 KB.

Bauen (Node läuft im Docker-Image von ILIAS Slim, auf dem Mac nicht nötig):

```bash
./dev/npm.sh install
./dev/npm.sh run build
```

## Gestaltung

- Ruhig, modular, editor-artig; bewusst nicht wie ein Präsentations-Werkzeug
  mit Vollflächen-Farbverläufen. Paletten nur als Akzent (Icons, Balken,
  Marker, Knöpfe), Flächen neutral.
- Vier Paletten mit je vier Stufen (`--uda-p0` hell … `--uda-p3` dunkel):
  „Ruhig & Natürlich“, „Modern & Klar“ (Standard), „Warm & Freundlich“,
  „Spielerisch & Frisch“. Weisse Schrift nur auf `p3`.
- Alle Klassen mit Präfix `uda-` (BEM-ähnlich), alles unter `.uda`, damit sich
  ILIAS- und Plugin-Stile nicht stören. ILIAS setzt `hyphens: auto`; `.uda`
  schaltet das aus.
- Rückfragen (Löschen, Beenden, Zurücksetzen) als Overlay (`<dialog>`).
- Vollbild: die Bühne füllt das Fenster, zusätzlich echtes Vollbild, wo der
  Browser es erlaubt. Pfeiltasten/Bild-auf/-ab (Presenter-Fernbedienungen)
  wechseln die Frage.
