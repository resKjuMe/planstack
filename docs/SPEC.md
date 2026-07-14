# Planstack — Spezifikation

Software zum **Online-Organisieren einer Roadmap** aus Tasks, Phasen und
Abhängigkeiten (Gates).

## Allgemeine Merkmale

- **User-Logins** mit **offener Registrierung**
- **Frontend:** Blade
- **Multi-Projekt** mit Projekt↔User-Zuweisung (n:m)
- **DB:** MariaDB / MySQL

## Domänenregeln

- Jeder User kann ein **Projekt** mit einem **unique Kürzel (`alias`)** anlegen und ist
  ab dort **Project-Owner**.
- Der Owner kann anderen Usern Zugriff auf das Projekt geben (Rolle in `users_to_projects`).
- **Tasks** tragen ein lesbares Kürzel (z.B. `C23`), einen Aufwand (Personentage /
  Story Points / geschätzte Tokens) und durchlaufen einen Status-Workflow.
- **Requirements** modellieren Task-Abhängigkeiten (ein Task hat 0..n Vorbedingungs-Tasks).
- **Concerns** dokumentieren Blocker/Fehleinschätzungen und offene Owner-Entscheidungen (1:1 zum Task).

---

## Datenbank-Schema

> Hinweis: Die Quell-Spezifikation nennt `nvarchar(max)` (SQL-Server-Terminologie).
> Auf MariaDB/MySQL wird dies als `TEXT`/`LONGTEXT` umgesetzt.

### `users` (Laravel-Default)

Standard-Laravel-`users`-Tabelle (id, name, email, password, …).

### `projects`

| Spalte      | Typ            | Anmerkung                     |
|-------------|----------------|-------------------------------|
| id          | bigint (PK)    |                               |
| created_by_id | FK → users.id| Project-Owner                 |
| alias       | varchar, unique| eindeutiges Kürzel            |
| name        | varchar(100)   |                               |
| description | text, nullable |                               |
| created_at  | timestamp      |                               |
| updated_at  | timestamp      |                               |

### `teams` (Ergänzung*)

| Spalte        | Typ            | Anmerkung        |
|---------------|----------------|------------------|
| id            | bigint (PK)    |                  |
| created_by_id | FK → users.id  | Team-Creator     |
| name          | varchar(100)   |                  |
| created_at    | timestamp      |                  |
| updated_at    | timestamp      |                  |

Mitglieder über `users_to_teams` (n:m). Der Creator fügt Mitglieder **ausschließlich per E-Mail** hinzu.

### `projects_to_teams` (n:m, Ergänzung*)

Weist Projekten Teams zu. **Zugriff** auf ein Projekt = Owner oder Mitglied eines
zugewiesenen Teams.

| Spalte     | Typ              |
|------------|------------------|
| id         | bigint (PK)      |
| project_id | FK → projects.id |
| team_id    | FK → teams.id    |
| created_at | timestamp        |
| updated_at | timestamp        |

### `users_to_projects` (n:m)

> Rolle-Tabelle: hält **nur noch die Rolle** eines Users im Projekt (Zugriff kommt über
> `projects_to_teams`). Ohne expliziten Eintrag gilt für Team-Mitglieder die Rolle **WORKER**.

| Spalte     | Typ                          | Anmerkung                          |
|------------|------------------------------|------------------------------------|
| id         | bigint (PK)                  |                                    |
| user_id    | FK → users.id                |                                    |
| project_id | FK → projects.id             |                                    |
| role       | enum(WORKER, ARCHITECT, ADMIN)|                                   |
| created_at | timestamp                    |                                    |
| updated_at | timestamp                    |                                    |

Unique: (`user_id`, `project_id`).

### `tasks`

| Spalte               | Typ                     | Anmerkung                              |
|----------------------|-------------------------|----------------------------------------|
| id                   | bigint (PK)             |                                        |
| project_id           | FK → projects.id        | Zugehöriges Projekt (Ergänzung*)       |
| created_by_id        | FK → users.id           |                                        |
| claimed_by_id        | FK → users.id, nullable |                                        |
| name                 | varchar                 | lesbares Kürzel, z.B. "C23"            |
| summary              | varchar(255)            |                                        |
| description          | longtext, nullable      | `nvarchar(max)`                        |
| description_acceptance_criteria | longtext, nullable | Akzeptanzkriterien (Ergänzung*)  |
| phase_id             | bigint, nullable        |                                        |
| effort_man_days      | decimal(4,1), nullable  | Personentage (fraktional möglich)      |
| effort_story_points  | int, nullable           | Story Points                           |
| effort_tokens        | int, nullable           | geschätzte Tokens                      |
| affected_files       | int, nullable           | geschätzte Anzahl betroffener Dateien  |
| pr_number            | string(20), nullable    | GitHub-PR-Nummer (verlinkt über project.github_repo) |
| status               | enum (s.u.)             |                                        |
| created_at           | timestamp               |                                        |
| updated_at           | timestamp               |                                        |
| claimed_at           | timestamp, nullable     |                                        |
| merged_at            | timestamp, nullable     |                                        |

**status-Enum:** `UNKNOWN, BLOCKED, CONCERNED, PICKABLE, CLAIMED, ANALYZING, IN_PROGRESS, IN_REVIEW, COMPLETED, MERGED`

### `phases` (Ergänzung*)

Phasen eines Projekts; `tasks.phase_id` referenziert diese Tabelle.

| Spalte     | Typ              | Anmerkung          |
|------------|------------------|--------------------|
| id         | bigint (PK)      |                    |
| project_id | FK → projects.id |                    |
| name       | varchar(100)     |                    |
| position   | int, default 0   | Reihenfolge        |
| created_at | timestamp        |                    |
| updated_at | timestamp        |                    |

> \* `tasks.project_id` und die `phases`-Tabelle sind Ergänzungen gegenüber der
> ursprünglichen Spec, damit Tasks einem Projekt/einer Phase zugeordnet werden können
> (für das Multi-Projekt-Board erforderlich).

### `task_requirements` (1:n)

Verknüpft einen Task mit einem Vorbedingungs-Task (parent).

| Spalte     | Typ              | Anmerkung                    |
|------------|------------------|------------------------------|
| id         | bigint (PK)      |                              |
| task_id    | FK → tasks.id    | der abhängige Task           |
| parent_id  | FK → tasks.id    | die Vorbedingung             |
| created_at | timestamp        |                              |
| updated_at | timestamp        |                              |

### `task_concerns` (1:1)

| Spalte                  | Typ                | Anmerkung                                             |
|-------------------------|--------------------|-------------------------------------------------------|
| id                      | bigint (PK)        |                                                       |
| task_id                 | FK → tasks.id, unique |                                                    |
| created_by_id           | FK → users.id      |                                                       |
| summary                 | varchar(255)       |                                                       |
| description_context     | longtext, nullable | bereits gesammelte Hintergründe                       |
| description_blocker     | longtext, nullable | weshalb es blockiert                                  |
| description_misconception | longtext, nullable | weshalb die Planung falsch war                      |
| description_decisions   | longtext, nullable | offene Owner-Entscheidungen; 1 pro Zeile, Optionen als CSV, z.B. `1. Welchen Weg gehen?;Option A desc;Option B desc` |
| created_at              | timestamp          |                                                       |
| updated_at              | timestamp          |                                                       |
