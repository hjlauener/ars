# Udon ARS – ILIAS 11 Repository-Plugin

Repository-Objekt-Plugin für **ILIAS 11**, mit dem sich im Magazin ein Objekt
vom Typ **„Udon ARS“** hinzufügen lässt.

| | |
|---|---|
| Plugin-Name | `UdonArs` |
| Anzeigename | Udon ARS |
| Plugin-ID / Objekttyp | `xuda` |
| Slot | `Services/Repository/RepositoryObject` (`robj`) |
| ILIAS | 11.0 – 11.999 |
| PHP | ≥ 8.3 |

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

Gerüst (Version 0.1.0): Ein Udon ARS lässt sich anlegen; es hat die Reiter
„Inhalt“ (noch Platzhalter), „Info“, „Einstellungen“ (Titel, Beschreibung)
und „Rechte“.
