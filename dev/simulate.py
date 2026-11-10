#!/usr/bin/env python3
"""
Simuliertes Publikum für Udon ARS – zum Ausprobieren und Üben einer
Präsentation ohne echte Teilnehmende (nur Python 3, keine Pakete nötig).

  ./dev/simulate.py 123456                 20 Personen beantworten die aktuelle Frage
  ./dev/simulate.py 123456 -n 50           50 Personen
  ./dev/simulate.py 123456 --live          bleiben dabei und beantworten jede neue
                                           Frage nach ein paar Sekunden (Ctrl+C beendet)
  ./dev/simulate.py 123456 --url https://ilias.example.org

Die Personen nehmen ohne Anmeldung teil (Plugin-Konfiguration: «Teilnahme ohne
Anmeldung erlauben»). Fürs Quiz geben einige einen Namen an.
"""

import argparse
import json
import random
import threading
import time
import urllib.request

PLUGIN_PATH = "/Customizing/global/plugins/Services/Repository/RepositoryObject/UdonArs/join.php?a=api"
NAMES = ["Anna", "Ben", "Chiara", "Dario", "Elif", "Fabio", "Greta", "Hugo", "Ines", "Jonas", "Kim", "Lena", "Mia", "Noah"]
WORDS = ["Klarheit", "Geduld", "Begeisterung", "Struktur", "Humor", "Dialog", "Neugier", "Fairness", "Praxis", "Feedback"]


def api(url, body):
    request = urllib.request.Request(url, json.dumps(body).encode(), {"Content-Type": "application/json"})
    with urllib.request.urlopen(request, timeout=15) as response:
        return json.load(response)


def answer_for(question):
    ids = [a["id"] for a in question["answers"]]
    kind = question["type"]
    if kind == "choice":
        if question["settings"].get("multiple"):
            return random.sample(ids, random.randint(1, min(2, len(ids))))
        # earlier answers a bit more often, so that the result is not flat
        return [random.choices(ids, weights=range(len(ids), 0, -1))[0]]
    if kind == "quiz":
        return [random.choice(ids)] if random.random() < 0.4 else [ids[0]]
    if kind == "rank":
        order = ids[:]
        order.sort(key=lambda i: ids.index(i) + random.random() * len(ids))
        return order
    words = random.choices(WORDS, weights=range(len(WORDS), 0, -1), k=question["settings"].get("maxWords", 3))
    return list(dict.fromkeys(words))


class Person(threading.Thread):
    def __init__(self, url, code, number, live, stop):
        super().__init__(daemon=True)
        self.url, self.code, self.live, self.stop = url, code, live, stop
        self.token = f"simuliert{random.getrandbits(64):016x}{number:04d}"
        self.name = random.choice(NAMES) if random.random() < 0.6 else ""
        self.answered = set()
        self.result = "–"

    def call(self, **body):
        return api(self.url, {"code": self.code, "token": self.token, **body})

    def run(self):
        named = False
        while not self.stop.is_set():
            try:
                state = self.call(action="state")
                if state.get("status") == "unknown":
                    self.result = "Code unbekannt"
                    return
                if state.get("status") == "login":
                    self.result = "Teilnahme nur mit Anmeldung"
                    return
                if state.get("hasQuiz") and self.name and not named:
                    self.call(action="name", name=self.name)
                    named = True
                question = state.get("question")
                if question and state.get("voting") and not state.get("myAnswer") and question["id"] not in self.answered:
                    if self.live:
                        time.sleep(random.uniform(1.5, 8))
                    reply = self.call(action="answer", questionId=question["id"], answer=answer_for(question))
                    self.answered.add(question["id"])
                    self.result = reply.get("error") or "beantwortet"
                elif not self.live:
                    self.result = "keine offene Frage" if not question or not state.get("voting") else "schon beantwortet"
                if not self.live:
                    return
            except Exception as error:  # network problems: try again
                self.result = f"Fehler: {error}"
                if not self.live:
                    return
            self.stop.wait(2)


def main():
    parser = argparse.ArgumentParser(description="Simuliertes Publikum für Udon ARS")
    parser.add_argument("code", help="sechsstelliger Teilnahme-Code")
    parser.add_argument("-n", type=int, default=20, help="Anzahl Personen (Standard 20)")
    parser.add_argument("--live", action="store_true", help="dabei bleiben und jede neue Frage beantworten")
    parser.add_argument("--url", default="http://localhost:8080", help="Adresse von ILIAS (Standard http://localhost:8080)")
    args = parser.parse_args()

    url = args.url.rstrip("/") + PLUGIN_PATH
    code = "".join(c for c in args.code if c.isdigit())
    stop = threading.Event()
    people = [Person(url, code, i, args.live, stop) for i in range(args.n)]
    for person in people:
        person.start()
        time.sleep(0.02)
    try:
        if args.live:
            print(f"{args.n} Personen sind dabei. Ctrl+C beendet.")
            while True:
                time.sleep(5)
                answers = sum(len(p.answered) for p in people)
                print(f"  bisher {answers} Antworten")
        for person in people:
            person.join()
    except KeyboardInterrupt:
        stop.set()
    summary = {}
    for person in people:
        summary[person.result] = summary.get(person.result, 0) + 1
    for result, count in sorted(summary.items(), key=lambda x: -x[1]):
        print(f"{count:4d} × {result}")


if __name__ == "__main__":
    main()
