# Future Drafts — Design

**Date:** 2026-04-27
**Status:** Approved for implementation

## Overview

A WordPress plugin that adds a dedicated dashboard widget called **Future Drafts**. At the moment of an experience — a trip, a project launch, a life event — the user drops a lightweight draft (title + a few notes) and picks a date. On that date the entry resurfaces in the same widget under "Ready to write," where the user can finish writing it as a full post.

Tagline: *Drafts for your future self.*

Companion plugin: works alongside [Draft Sweeper](https://github.com/annezazu/draft-sweeper/), which is updated to ignore Future Drafts via a `meta_query` exclusion.

## Goals

- Low-friction capture *now*, guided writing *later*.
- Reuse WordPress's native draft infrastructure rather than inventing a parallel system.
- Surface entries quietly in the dashboard widget — no nags, no admin-wide notices.
- Per-user privacy: the feature is intimate; each author sees only their own entries.

## Non-goals (v1)

- Email reminders (planned for v2).
- Site-wide / multi-author shared lists.
- In-widget post editing (beyond title/content during capture).
- Mobile / Gutenberg-block surface.

---

## Architecture

### Form factor

Standalone plugin (`future-drafts/`). PHP backend + small React island for the widget UI. Targets modern WordPress (Gutenberg components available in admin).

### Data model

Pending entries are stored as **standard `post_type=post` + `post_status=draft`** records. The only thing that makes a post a Future Draft is a single post meta key:

- **`_future_draft_remind_on`** — `string`, ISO date `YYYY-MM-DD`, in the site's timezone (`wp_timezone()`).
  - Presence of the key → it's a Future Draft.
  - Absence → regular draft.

No custom post type. No custom post status. No custom table.

**Why this works:**
- Reuses revisions, autosaves, the post-edit screen, and capabilities for free.
- "Finish writing →" is just `post.php?post=<id>&action=edit`.
- Snooze = update the meta to a new date.
- On publish or trash, a hook deletes the meta so the post graduates back into normal life.

### Per-user scoping

All `WP_Query` and REST calls filter by `'author' => get_current_user_id()`. Server-side enforcement (not just UI). Capability check: `edit_posts`. Cross-user reads return 403.

### File layout

```
future-drafts/
  future-drafts.php          # Bootstrap, activation
  src/
    Plugin.php
    PostMeta.php             # Register _future_draft_remind_on, sanitization
    Dashboard/
      Widget.php             # wp_add_dashboard_widget, asset enqueue
    Rest/
      Controller.php         # CRUD endpoints
    Hooks/
      CleanupOnPublish.php   # Delete meta on publish/trash
  assets/
    src/widget.tsx
    src/components/...
  build/                     # wp-scripts output
  tests/
  composer.json
  package.json
```

---

## UI: widget states & interactions

The widget has three regions stacked inside a single dashboard tile titled **"Future Drafts"** with subtitle *"Drafts for your future self."*

### Region 1 — Capture form (always visible at top)

```
┌────────────────────────────────────────┐
│ Title                  [____________]  │
│ What's coming up for   [____________]  │
│ you?                   [____________]  │
│ Remind me on  [+1w][+1m][+3m]          │
│               [ DatePicker calendar ]  │
│                                        │
│                       [Save for later] │
└────────────────────────────────────────┘
```

- **Title** — `TextControl`, single line.
- **What's coming up for you?** — `TextareaControl` (~3 rows). Placeholder: *"A few notes for your future self…"*
- **Remind me on** — preset chips (`Button`, secondary variant) above an inline `DatePicker` from `@wordpress/components`. Clicking a chip sets the calendar's date.
- **Save for later** — primary `Button`. Disabled until *(title or content)* and a date are both set. On submit: POST to REST, clear form, prepend new entry to Region 3.

### Region 2 — "Ready to write" (only when there are due entries)

Visually emphasized — light tinted background using `--wp-admin-theme-color` at low opacity. Each entry:

```
📝  Vacation in Lisbon                       [ Finish writing → ]
    "We hiked Sintra on the second day…"     [ Snooze ▾ ] [ × ]
    Saved Jan 12 · Resurfaced today
```

- **Finish writing →** opens `post.php?post=<id>&action=edit`.
- **Snooze ▾** opens a `MenuGroup` with `MenuItem`s: *+1 week / +1 month / +3 months / Custom date…* (last reveals an inline `DatePicker` in a `Popover`).
- **×** triggers `ConfirmDialog` then trashes the post.

### Region 3 — "Pending" (collapsed list of upcoming entries)

A `Collapsible` panel labeled e.g., *"3 drafts waiting"*. Collapsed by default once there are >2 pending. Each row:

```
Lisbon trip               · in 2 weeks   [ Snooze ▾ ] [ × ]
Q3 launch retro           · in 1 month   [ Snooze ▾ ] [ × ]
Mom's 70th birthday       · in 6 months  [ Snooze ▾ ] [ × ]
```

Title click opens the post in the editor (escape hatch for users who want to edit early). No "Finish writing" — these aren't due yet.

### Empty state

When the user has no Future Drafts, Regions 2 and 3 are hidden and Region 1 shows a friendly subhead:

> *Capture an experience now. We'll bring it back when you're ready to write about it.*

---

## Data flow

### REST API

Namespace: `future-drafts/v1`. All endpoints require `edit_posts` and operate only on the current user's posts.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/entries` | Returns current user's Future Drafts as `{ due[], pending[] }`. Each item: `{ id, title, excerpt, remind_on, edit_url, status }`. |
| `POST` | `/entries` | Create. Body: `{ title, content, remind_on }`. Server creates a `draft` post (author = current user), sets `_future_draft_remind_on`, returns the entry. |
| `POST` | `/entries/:id/snooze` | Body: `{ remind_on }`. Updates the meta. 403 if not the author. |
| `DELETE` | `/entries/:id` | Trashes the post. 403 if not the author. |

We deliberately don't expose a generic "update" — title/content edits go through the post editor, not the widget. The widget's job is capture and surfacing.

### Due-date logic

- `_future_draft_remind_on` is `YYYY-MM-DD` (no time component).
- "Due" = `remind_on <= today` in `wp_timezone()`, computed at request time.
- Sort: due by `remind_on ASC` (oldest first); pending by `remind_on ASC` (soonest first).

### Cleanup on publish/trash

```php
add_action('transition_post_status', function ($new, $old, $post) {
    if ($post->post_type !== 'post') return;
    if (!in_array($new, ['publish', 'trash'], true)) return;
    delete_post_meta($post->ID, '_future_draft_remind_on');
}, 10, 3);
```

Publishing or trashing removes the meta, so the post graduates back into normal life (visible to Draft Sweeper if it lands as a draft for any reason).

### No cron in v1

Resurfacing is entirely query-time: each dashboard load recomputes due-vs-pending. No `wp_cron`. (v2 email reminders will need a daily scheduled hook scanning for newly-due entries.)

### Asset enqueue

`Dashboard/Widget.php` enqueues the React bundle only on `index.php`. Dependencies: `wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`. CSS includes `wp-components` for design-system styles.

---

## Testing

### PHP (PHPUnit + WP test suite via wp-env)

**`Rest/ControllerTest.php`** (most important):
- `GET /entries` returns only the current user's posts.
- `GET /entries` correctly splits `due` vs `pending` against `wp_timezone()`.
- `POST /entries` creates a `draft` with the meta set; rejects empty title+content; rejects malformed dates.
- `POST /entries/:id/snooze` updates meta; 403 for non-author; 404 for missing.
- `DELETE /entries/:id` trashes; same auth checks.
- All endpoints 401/403 without `edit_posts`.

**`Hooks/CleanupOnPublishTest.php`**:
- Publishing a Future Draft deletes the meta.
- Trashing a Future Draft deletes the meta.
- Other status transitions (e.g., `draft` → `pending`) do **not** delete the meta.
- Posts without the meta are unaffected.

**`PostMetaTest.php`** — meta registered with sanitization rejecting malformed dates.

### JS (Jest + React Testing Library)

- Form submit disabled until valid; clears on success.
- Snooze menu opens; preset click hits the right endpoint.
- "Ready to write" hidden when no due entries.
- Pending list collapses when >2 entries.
- Empty state copy renders at zero entries.

### Playground / manual

Ship `playground/blueprint.json` seeding entries: one due today, one due yesterday, one in 2 weeks, one in 6 months. Manual checklist:

1. Capture form: title-only, content-only, both — date required.
2. Preset chips fill calendar; calendar override works.
3. Due entry appears in "Ready to write."
4. "Finish writing →" opens editor with content intact.
5. Publishing the post removes it from the widget on next reload.
6. Snooze options advance the date correctly.
7. Delete prompts confirm; trashing succeeds.
8. Second user sees only their own entries.

## Acceptance criteria (v1)

- [ ] User can capture a title + content + date in <3 clicks (chip + Save).
- [ ] Entries past their date appear in "Ready to write" with one-click access to the editor.
- [ ] Snooze advances the date by preset or custom date.
- [ ] Publishing or trashing a Future Draft removes the meta.
- [ ] Future Drafts are invisible to Draft Sweeper (verified with the companion plugin installed).
- [ ] Each user sees only their own entries; cross-user access returns 403.
- [ ] Widget renders cleanly with 0, 1, and 50+ entries.
- [ ] All UI uses `@wordpress/components` primitives (no hand-rolled controls).

## Companion change to Draft Sweeper

A separate issue will be opened on the `draft-sweeper` repo to add a `meta_query` exclusion in `src/Drafts/DraftRepository.php`:

```php
'meta_query' => [
    [ 'key' => '_future_draft_remind_on', 'compare' => 'NOT EXISTS' ],
],
```

This makes Draft Sweeper ignore any draft with the Future Drafts meta. Pending and due entries both stay invisible to it (the due ones live in the Future Drafts widget under "Ready to write"). Once published or trashed, the meta is gone and Draft Sweeper sees them again normally.

## v2 / future ideas (out of scope)

- Daily cron + one-time email reminder ("Your future draft about *X* is ready to finish").
- Filter hook (e.g., `draft_sweeper_query_args`) so other plugins can register their own "not abandoned" meta keys.
- AI-assisted writing prompt on the editor screen for due Future Drafts ("Here are the notes you left yourself…").
- A per-user setting for default snooze interval.
