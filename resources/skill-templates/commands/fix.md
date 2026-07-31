## Fix (`/planstack fix [<PROJECT>] <TASK|PR-NUMMER>`)

Bringt einen offenen PR wieder in mergefähigen Zustand — alles über `gh`/`git` am PR, nichts serverseitig. `<TASK|PR-NUMMER>` ist **erforderlich** (kein Auto-Pick).

1. **PR bestimmen** — dafür gibt es gezielte Endpunkte, **keine** Task-Listen durchsuchen:
   - numerisches Argument → `GET $BASE/projects/$PROJ/tasks/by-pr/<pr>` liefert den Task zu dieser PR-Nummer.
   - Task-Name → `GET $BASE/projects/$PROJ/tasks/<name>` (oder `.../tasks/by-name/<name>`) → dessen `pr_number`.
   - **Ohne `<PROJECT>`**: `GET $BASE/projects` auflisten (kleine Antwort) und denselben gezielten Aufruf je Projekt versuchen, bis einer trifft — `404` heißt nur „nicht in diesem Projekt".
2. **Merge-Konflikte zum Ziel-Branch:** Head-Branch auschecken, Target-Branch ziehen und einmergen (`git fetch` + `git merge origin/<base>`), Konflikte auflösen, committen, pushen.
3. **Kommentare UND Review-Kommentare** — beide Arten:
   - **PR-/Issue-Kommentare** (`gh pr view --comments` bzw. `gh api repos/{owner}/{repo}/issues/{pr}/comments`): jeden fachlich beantworten und, wo nötig, den Code fixen.
   - **Review-Kommentare** (inline an Codezeilen, `gh api repos/{owner}/{repo}/pulls/{pr}/comments`): jeden beantworten, den Code fixen und den Thread **auflösen** (GraphQL `resolveReviewThread`).

   Grundsatz: alles Offene beantworten + fixen, Review-Threads zusätzlich resolven.
4. **Fehlschlagende CI:** `gh pr checks` prüfen, rote Checks lokal reproduzieren, korrigieren, pushen, bis die CI grün ist.

**Fortschritts-Events (best-effort) — mit Zähler:** zu Beginn `ev <id> POLISHING`, nach grüner CI + beantworteten Kommentaren `ev <id> POLISHED` (`<id>` = numerische Task-id).

Solange die Politur läuft, wird `POLISHING` bei **jeder gezählten Einheit** erneut abgesetzt — mit demselben Bruch und derselben Prozentzahl, die auch in die Statuszeile gehen (die Zahlen liegen dort ohnehin vor, siehe „Sticky-Statuszeile"):

```bash
ev <id> POLISHING "3/7 Kommentare: TaskController.php" 43
ev <id> POLISHING "2/5 Checks: phpstan" 40
```

Das ist der **einzige** Weg, auf dem der Fortschritt aufs Board kommt: `fix` claimt nicht, also gibt es keinen Claim-Fortschritt, an dem man ihn ablesen könnte. Ohne die Zusatzangaben bleiben `progress_detail`/`progress_percent` am Task leer und die Karte zeigt nur, **dass** eine Session arbeitet, nicht **wie weit**. Ohne echten Nenner die Prozentzahl weglassen (nie schätzen) — der Detailtext allein ist trotzdem wertvoll.

Danach ggf. via `/planstack review` erneut prüfen.
