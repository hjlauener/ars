# UdonArs – Projektregeln

ILIAS Repository-Objekt-Plugin (Name `UdonArs`, Anzeigename „Udon ARS“,
ID `xuda`): ein schlankes Audience-Response-System. Ziel-Version: ILIAS 11
(lokale Testumgebung des Auftraggebers: ILIAS 11 / ILIAS Slim in Docker,
dasselbe wie beim Udon Forum). Aufbau und Arbeitsweise wie beim Udon Forum
(`hjlauener/forum`) und beim Udon Whiteboard (`hjlauener/whiteboard`).
GitHub: `hjlauener/ars` (privat).

## Stand

Version 0.3.1: vier Fragetypen (Auswahlfrage, Wortwolke, Rangfolge, Quiz),
Reiter „Fragen“, „Präsentieren“, „Ergebnisse“ (bzw. „Teilnehmen“ mit
Leserecht), „Info“, „Einstellungen“, „Rechte“; Teilnahme-Seite `join.php`;
Plugin-Konfiguration. Technik siehe `TECHNIK.md`.

## Wünsche des Auftraggebers

- **Einfach halten**: so viele Optionen wie nötig, nicht wie Mentimeter. Keine
  Teilen-Dialoge, Einladungen, Galerien, Teams; Rechte über ILIAS.
- Bedienkonzept: Präsentation mit mehreren Fragen nacheinander; die vier
  Fragetypen sollen nicht an Mentimeter erinnern (ruhig, modular,
  editor-artig, Paletten nur als Akzent; siehe `TECHNIK.md`, Gestaltung).
- Die vier Farbpaletten (Werte in `ilUdaPalettes`) sind vorgegeben.

## Aufbau

- **PHP (`classes/`)**: ILIAS-Einbindung, Rechte, Speichern, Auswertung.
  Logik in `ilUdaService`, Datenbank in `ilUdaRepository`, Teilnahme in
  `ilUdaJoinEndpoint` (über `join.php`).
- **TypeScript (`frontend/`)**: Vue 3, gebaut mit Vite nach `js/dist/`
  (wird committet, damit `uda` ohne Node funktioniert).
  **Nach jeder Änderung in `frontend/` neu bauen und `js/dist/` mitcommitten:**

  ```bash
  ./dev/npm.sh install
  ./dev/npm.sh run build
  ```

- `TECHNIK.md` (Bibliotheken, Versionen, Lizenzen, Schnittstellen, Datenbank)
  bei jeder Änderung daran nachführen.
- Texte der Oberfläche kommen aus den Sprachdateien: Schlüssel mit `ui_`
  gehen automatisch ans Frontend (dort ohne `ui_`, `t("key")`). Platzhalter
  `%s`, Mehrzahl über `_one` / `_other` und `tn("key", n)`. Keine `{…}` in
  Texten (die ILIAS-Seitenvorlage entfernt sie).
- Alle CSS-Klassen mit Präfix `uda-`, alles unter `.uda`.
- Teilnehmende: nur zusammengefasste Ergebnisse an die Geräte, keine
  Benutzer-IDs in Antworten, Tokens nur gehasht speichern.
- Jede Änderung einer Durchführung über `ilUdaRepository::updateSession`
  (schreibt die Signaldatei); sonst merken die Smartphones sie erst nach
  bis zu 10 s.

## Sprachdateien

- **Jeder neue oder geänderte Text kommt immer in alle drei Dateien:**
  `lang/ilias_de.lang`, `lang/ilias_en.lang` und `lang/ilias_fr.lang`. Die
  Schlüssel aller Dateien müssen identisch sein (gleiche Reihenfolge).
- Deutsch ohne englische Begriffe, Schweizer Schreibweise (ss statt ß).
  („Udon ARS“ als Name des Objekts bleibt in allen Sprachen gleich; „Quiz“ ist
  auch deutsch.)
- Kein Text fest im PHP-Code; Plugin-Texte über `$this->txt("key")`.
- Die Dateien sind in Abschnitte gegliedert (`# --- Abschnitt ---`; Zeilen mit
  `#` am Anfang ignoriert ILIAS). Neue Schlüssel in den passenden Abschnitt,
  in allen Dateien an derselben Stelle. Deutsch mit „Sie“, Englisch britisch,
  Französisch mit „vous“, geschütztes Leerzeichen (U+00A0) vor : ? ! und
  innerhalb « ».

Prüfen:

```bash
diff <(cut -d'#' -f1 lang/ilias_de.lang) <(cut -d'#' -f1 lang/ilias_en.lang)
diff <(cut -d'#' -f1 lang/ilias_de.lang) <(cut -d'#' -f1 lang/ilias_fr.lang)
```

## Oberfläche

- Rückfragen (z.B. Löschen) als Overlay (im Frontend `ConfirmDialog.vue`,
  sonst Interruptive Modal wie im ILIAS-Kern), keine eigenen
  Bestätigungsseiten.
- Nur UI-Aufrufe verwenden, die in ILIAS 11 funktionieren
  (z.B. kein `$f->legacy(...)` mit alter Signatur).

## Datenbank

- Neue DB-Schritte in `sql/dbupdate.php` anhängen und die Version in
  `plugin.php` erhöhen, sonst führt ILIAS sie nicht aus.
- Tabellen mit Präfix `rep_robj_xuda_`.
- Spaltennamen nicht aus der Liste reservierter Wörter (z.B. `status`).

## Entwickeln / Testen

- Der Auftraggeber testet mit `uda` (holt den Branch und lädt das Plugin im
  lokalen Docker-ILIAS neu), siehe `dev/README.md`. Ohne Pull:
  `./dev/reload.sh`.
- PHP gibt es auf dem Mac nicht; prüfen im ILIAS-Slim-Image:
  `docker run --rm -v "$PWD:/plugin" -w /plugin --entrypoint sh ilias-slim-web:latest -c 'for f in join.php plugin.php sql/dbupdate.php classes/*.php; do php -l "$f"; done'`
- Simuliertes Publikum (auch zum Üben): `./dev/simulate.py 123456 -n 30`
  (einmal antworten) oder `--live` (bleibt dabei, beantwortet jede neue Frage).
- Teilnahme lässt sich ohne Browser testen:
  `curl -X POST -H 'Content-Type: application/json' -d '{"action":"state","code":"123456","token":"abcdefghijklmnop"}' 'http://localhost:8080/Customizing/global/plugins/Services/Repository/RepositoryObject/UdonArs/join.php?a=api'`
- Vor jedem Push: `php -l` auf alle PHP-Dateien, den Sprachdatei-Check oben
  und `./dev/npm.sh run build` (prüft auch die Typen).
