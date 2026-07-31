## Review (`/planstack review [<PROJECT>] [<TASK>]`)

Reviewt Tasks, die **zum Review bereitliegen**: Pool-Status `REVIEWBAR` (die Spalte *vor* `IN_REVIEW`) oder ein noch nicht übernommener `IN_REVIEW` — jeweils mit PR. **Eigene Tasks sind nicht reviewbar** — `review-next` überspringt sie, ein gezielter Aufruf darauf wird abgelehnt. Das Übernehmen setzt nur `reviewed_by`; die Verschiebung nach `IN_REVIEW` löst das `REVIEWING`-Event über die Org-Automation aus.

1. **Task wählen & Review übernehmen:**
   - `<PROJECT> <TASK>` → `POST $BASE/projects/$PROJ/tasks/$TASK/review-claim`.
   - nur `<PROJECT>` → `POST $BASE/projects/$PROJ/review-next`. Ein **schon selbst übernommener**, noch nicht abgeschlossener Review kommt **zuerst** und wird fortgesetzt; mit erfasstem Ergebnis kommt der Task nicht erneut.
   - **weder noch** → projektübergreifend: `GET $BASE/projects` auflisten und `review-next` pro Projekt, bis eines liefert.

   `{"reviewing": null}` bzw. leer ⇒ nichts zu reviewen. Nach dem Übernehmen `ev <id> REVIEWING` melden (best-effort).
2. **Review ausführen:** den **Review-Skill** (`/review`) für den PR laufen lassen — Strenge gemäß `review_strictness`, Prüftiefe gemäß `review_thoroughness`. Die Antwort aus Schritt 1 trägt **immer** `pr_number` (und `pr_url`, sofern Repo konfiguriert), unabhängig von `task.fields`. Ergebnis = Empfehlung (`APPROVE`/`REQUEST_CHANGES`) + ausführliche Analyse.
3. **Review vorlegen & Empfehlung festlegen** gemäß `review_auto_status`: bei `auto` die abgeleitete Empfehlung direkt verwenden. Bei `manual` (Default) dem Nutzer **zuerst die vollständige Review anzeigen** (Aufbau wie Schritt 4) und **erst danach** die Empfehlung bestätigen lassen. **Grundsatz: nie nach der Entscheidung fragen, ohne die Review vorher gezeigt zu haben** — und solange die Settings das Ablegen nicht automatisch festlegen, wird nichts in Task oder PR geschrieben, bevor der Nutzer bestätigt hat.
4. **Ergebnis erfassen:** `POST $BASE/projects/$PROJ/tasks/$TASK/review` mit `{"recommendation":"APPROVE|REQUEST_CHANGES","summary":"…"}` — füllt `last_reviewed_at`, `last_review_recommendation`, `last_review_summary`. `summary` ist **keine Kurzbeschreibung**, sondern die **ausführliche Review-Analyse**, in dieser Reihenfolge:
   1. `Review-Konfiguration: Strenge=<review_strictness>, Gründlichkeit=<review_thoroughness>, Modell=<tatsächlich genutztes Claude-Modell>, Effort=<Reasoning-Aufwand>` (vorab, damit das Review nachvollziehbar ist).
   2. `TLDR: <Kernaussage in 1–3 Sätzen>`.
   3. **Ausführliche Analyse** — Befunde je Datei/Aspekt, Begründungen, Risiken, Vorschläge.
5. **Ablage gemäß `review_results`:** `task_only` = nur der Task. `task_and_pr` = zusätzlich am PR (`gh pr review <pr> --approve` bzw. `--request-changes` mit der Zusammenfassung).

**Fortschritts-Events (best-effort):** nach Schritt 4 `ev <id> REVIEWED`, danach `ev <id> APPROVED` bzw. `ev <id> CHANGES_REQUESTED`.
