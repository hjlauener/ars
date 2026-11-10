# Udon ARS – ILIAS 11 Repository-Plugin

Ein schlankes **Audience-Response-System** für ILIAS 11 / ILIAS Slim: Eine
Präsentation mit Fragen, die Teilnehmenden antworten auf dem Smartphone über
einen Code oder QR-Code, die Ergebnisse erscheinen live auf dem Beamer.

| | |
|---|---|
| Plugin-Name | `UdonArs` |
| Anzeigename | Udon ARS |
| Plugin-ID / Objekttyp | `xuda` |
| Slot | `Services/Repository/RepositoryObject` (`robj`) |
| ILIAS | 11.0 – 11.999 |
| PHP | ≥ 8.3 |

## Funktionen

- **Vier Fragetypen**: Auswahlfrage (eine oder mehrere Antworten), Wortwolke,
  Rangfolge (Ziehen oder Pfeile), Quiz (richtige Antwort, Zeitlimit, Punkte,
  Rangliste)
- **Fragen** (Reiter): Fragetyp wählen, Fragen bearbeiten, umstellen,
  duplizieren; speichert automatisch
- **Präsentieren**: Code und QR-Code, Live-Ergebnisse, Abstimmung öffnen /
  schliessen, Ergebnisse zeigen / verbergen, Antworten zurücksetzen,
  Vollbild, Pfeiltasten
- **Teilnehmen**: Seite fürs Smartphone, mit oder ohne Anmeldung; der Code
  bleibt gleich und lässt sich ausdrucken
- **Ergebnisse**: jede Durchführung einzeln, CSV-Export, Löschen
- **Einstellungen** je Udon ARS: Farbpalette, Ergebnisse sofort oder nach
  Freigabe, neuer Code
- **Plugin-Konfiguration**: Teilnahme ohne Anmeldung, verfügbare Fragetypen,
  Standard-Farbpalette, Ergebnisse automatisch löschen, kurze Adresse

Rechte: *Lesen* = teilnehmen, *Bearbeiten* = Fragen, Präsentieren,
Ergebnisse, Einstellungen.

Technische Details: **[TECHNIK.md](TECHNIK.md)**.

## Lokal entwickeln und testen

Wie beim Udon Forum und beim Udon Whiteboard: Der Terminal-Befehl **`uda`**
holt den neusten Stand von GitHub und lädt das Plugin im lokalen Docker-ILIAS
neu. Einrichtung und alle Befehle: **[dev/README.md](dev/README.md)**.

Kurzfassung:

```bash
mkdir -p ~/Projekte && cd ~/Projekte
```

```bash
git clone https://github.com/hjlauener/ars.git UdonArs
```

```bash
~/Projekte/UdonArs/dev/install-command.sh
```

Neues Terminal-Fenster öffnen, dann:

```bash
uda install
```

Danach genügt nach jeder Änderung:

```bash
uda
```

## Aktueller Stand

Version 0.3.1: alle vier Fragetypen, Präsentieren, Teilnahme mit und ohne
Anmeldung, Ergebnisse mit CSV-Export, Plugin-Konfiguration. Getestet mit 500
simulierten Smartphones (siehe TECHNIK.md).

Zum Ausprobieren ohne echtes Publikum:

```bash
./dev/simulate.py 123456 --live
```
