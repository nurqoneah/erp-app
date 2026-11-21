# ERP App — Frontend (React SPA)

React + TypeScript + Vite, Tailwind CSS + shadcn/ui, TanStack Query, React Router.

```bash
cp .env.example .env     # VITE_API_URL=http://localhost:8000/api
npm install
npm run dev              # http://localhost:5173
npm test                 # vitest
npm run lint             # oxlint
npm run build
```

Struktur:
- `src/app` — provider, router, auth, layout
- `src/components/ui` — komponen shadcn/ui
- `src/modules/{modul}` — halaman & API per modul (cermin `backend/modules`)
