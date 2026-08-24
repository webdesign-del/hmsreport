# India IVF HMS — Management Workspace (Next.js)

A rebuild of the static `management.html` workspace from `../india-ivf-hms` as a Next.js
App Router application — Next.js 16, React 19, TypeScript, Tailwind CSS v4.

## What this is

The Management workspace (Director / company-wide role) from the original static HMS site,
rebuilt as real routed pages with typed data and API routes, instead of one HTML file with
2,000+ lines of DOM-string-building JS. Scope matches the original handoff: Dashboard, Centre
Comparison, Aging Snapshot, Red Trigger Pile-up, Approval Queue, Booked Patient List, Booked
Patient Journey (with the donor/surrogate detail modal, mapped clinical/embryology forms,
patient communication & compliance tracking), Stage Patients and Prebook List drill-downs.
The other role dashboards (Doctor, Clinical Head, FC, Accounts) weren't part of the original
handoff and aren't included here.

## Architecture

- `lib/seed-data.ts` — the demo dataset (patients, prebook funnel, centres, aging, donors,
  surrogates, triggers, approvals…), typed.
- `lib/derive.ts` — pure derived-value functions ported from the original `data.js`/`app.js`
  (KYC generation, staging math, aging buckets, package categorisation, etc).
- `lib/journey-forms.ts` — the mapped clinical/embryology form + patient-communication /
  compliance definitions used by the Booked Patient Journey matrix.
- `app/api/*` — route handlers serving the seed data as JSON (scaffolding for a future real
  backend — swap the data source inside these routes without touching any page).
- `lib/api.ts` — typed server-side fetch helpers Server Components use to call those routes.
- `app/*` — one route per screen, mostly async Server Components that fetch + render; the
  interactive screens (filters, the journey matrix, the aging list) delegate to a
  `"use client"` component in `components/`.
- Styling is Tailwind CSS v4 (`@theme` tokens in `app/globals.css`) reproducing the original
  brand palette, plus a small `.tbl` component class for the wide data tables.

## Data persistence

There's still no real backend — the API routes return the same in-memory demo dataset the
original static site shipped with. Session-only edits (rescheduled journey dates, sent
patient-communication logs, compliance uploads) persist to `localStorage`, same as the
original page's behaviour.

## Getting started

```bash
npm install
npm run dev
```

Open http://localhost:3000 (falls back to the next free port if 3000 is busy).

```bash
npm run build   # production build
npm run lint    # ESLint
```
