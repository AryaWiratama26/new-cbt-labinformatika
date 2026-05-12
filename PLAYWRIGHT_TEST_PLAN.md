# Playwright Test Plan — CBT Laboratorium Informatika UPB

## Setup

```
tests/e2e/
├── playwright.config.ts
├── fixtures/
│   ├── auth.fixture.ts
│   └── seed.ts
├── pages/
│   ├── login.page.ts
│   ├── admin-dashboard.page.ts
│   ├── admin-students.page.ts
│   ├── admin-classrooms.page.ts
│   ├── admin-courses.page.ts
│   ├── admin-modules.page.ts
│   ├── admin-exams.page.ts
│   ├── student-dashboard.page.ts
│   └── student-exam.page.ts
├── specs/
│   ├── auth.spec.ts
│   ├── admin/
│   │   ├── dashboard.spec.ts
│   │   ├── students.spec.ts
│   │   ├── classrooms.spec.ts
│   │   ├── courses.spec.ts
│   │   ├── modules.spec.ts
│   │   ├── questions.spec.ts
│   │   ├── exams.spec.ts
│   │   ├── pdf-export.spec.ts
│   │   └── csv-export.spec.ts
│   └── student/
│       ├── dashboard.spec.ts
│       ├── exam-flow.spec.ts
│       └── security.spec.ts
└── data/
    └── import-test.csv
```

---

## Infrastructure

```ts
// playwright.config.ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  timeout: 30000,
  retries: 0,
  use: {
    baseURL: 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: 'php artisan serve --port=8000',
    url: 'http://localhost:8000',
    reuseExistingServer: true,
  },
});
```

```ts
// fixtures/seed.ts
import { execSync } from 'child_process';

export async function seedTestData() {
  execSync('php artisan migrate:fresh --seed --env=testing 2>&1', { stdio: 'inherit' });
  // Override admin password to known value
  execSync(`php artisan tinker --execute='
    $u = \\App\\Models\\User::where("role","admin")->first();
    $u->password = bcrypt("admin");
    $u->save();
  '`, { stdio: 'inherit' });
  // Create a test student
  execSync(`php artisan tinker --execute='
    \\App\\Models\\User::create([
      "username" => "teststudent",
      "name" => "Test Student",
      "role" => "mahasiswa",
      "classroom_id" => 1,
      "password" => bcrypt("test123"),
    ]);
  '`, { stdio: 'inherit' });
}

export async function cleanTestData() {
  execSync('php artisan migrate:fresh --env=testing 2>&1', { stdio: 'inherit' });
}
```

---

## Test Scenarios

### 1. Authentication (`specs/auth.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 1.1 | Login page renders | Visit `/login` | See branding, form fields, submit button |
| 1.2 | Admin login success | Fill admin/admin, submit | Redirect to `/admin/dashboard` |
| 1.3 | Student login success | Fill student credentials, submit | Redirect to `/student/dashboard` |
| 1.4 | Login failure | Wrong password | Stay on `/login`, see error message |
| 1.5 | Rate limiting | 6 failed attempts in 1 min | See rate limit error (429) |
| 1.6 | Logout | Click logout button | Redirect to `/login`, auth redirected to login |
| 1.7 | Redirect authenticated user | Visit `/login` when already logged in | Redirect to dashboard |

### 2. Admin Dashboard (`specs/admin/dashboard.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 2.1 | Dashboard stats render | Login as admin, visit dashboard | See stat cards: students, courses, classrooms, exams count |
| 2.2 | Score distribution chart | Dashboard renders | See 4 categories (<50, 50-70, 70-85, >85) |
| 2.3 | Top 5 students table | Dashboard renders | See ranked student list with scores |
| 2.4 | Recent activity feed | Dashboard renders | See latest sessions with timestamps |
| 2.5 | Quick access links work | Click each link | Navigate to correct page |
| 2.6 | Student import form exists | Dashboard renders | See CSV upload section |

### 3. Student Management (`specs/admin/students.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 3.1 | List students | Visit `/admin/students` | See students table with NIM, Nama, Kelas |
| 3.2 | Search students | Type in search box | Filter results in real-time |
| 3.3 | Filter by classroom | Select classroom dropdown | Show only students of that class |
| 3.4 | Create student | Fill form, submit | Student appears in list |
| 3.5 | Create student validation | Empty NIM or duplicate NIM | See validation errors |
| 3.6 | Edit student | Change name, save | See updated name in list |
| 3.7 | Delete student | Click delete, confirm | Student removed from list |
| 3.8 | Bulk delete | Select multiple, delete | All selected removed |
| 3.9 | Reset password | Click reset | Show success message |
| 3.10 | Move classroom | Select students, move to other class | Students updated in new class |
| 3.11 | Export CSV | Click export | Download CSV with correct columns |
| 3.12 | Import CSV | Upload valid CSV | Students created, see success |
| 3.13 | Import CSV invalid format | Upload malformed CSV | See error message |
| 3.14 | XSS prevention | Create student with `<script>` in name | Name displayed as text, not executed |

### 4. Classroom Management (`specs/admin/classrooms.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 4.1 | List classrooms | Visit `/admin/classrooms` | See classrooms with student count |
| 4.2 | Create classroom | Fill name + academic year + semester | Appears in list |
| 4.3 | Edit classroom | Change name, save | Updated in list |
| 4.4 | Delete classroom (empty) | Delete classroom without students | Removed from list |
| 4.5 | Delete classroom (has students) | Delete classroom with students | Prevented, see error |
| 4.6 | Classroom recap | Visit recap page | See matrix: students x exams with scores |
| 4.7 | Recap CSV export | Click download | CSV downloaded with correct data |

### 5. Courses (`specs/admin/courses.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 5.1 | List courses | Visit `/admin/courses` | See courses table (Kode, Nama) |
| 5.2 | Create course | Fill code + name, submit | Appears in list |
| 5.3 | Create course validation | Duplicate code | See error |
| 5.4 | Delete course | Click delete, confirm | Removed from list |

### 6. Modules & Questions (`specs/admin/modules.spec.ts`, `specs/admin/questions.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 6.1 | List modules per course | Click "Modul" on course | See modules with question count |
| 6.2 | Create module | Fill form, submit | Module appears |
| 6.3 | Delete module | Click delete, confirm | Module removed |
| 6.4 | List questions in module | Click "Kelola Soal" | See question bank |
| 6.5 | Create question manually | Fill content, options, correct answer, category | Question added to bank |
| 6.6 | Create question with image | Upload image | Question shows image |
| 6.7 | Edit question | Change content, save | Updated in bank |
| 6.8 | Delete question | Click delete, confirm | Removed from bank |
| 6.9 | Duplicate question | Click duplicate | Copy appears with "(copy)" suffix |
| 6.10 | Filter by category | Select "Mudah" filter | Only mudah questions shown |
| 6.11 | Search questions | Type search keyword | Filtered results |
| 6.12 | Import CSV questions | Upload valid CSV | Questions created |
| 6.13 | Import CSV with ZIP images | Upload CSV + ZIP | Questions + images created |
| 6.14 | Import DOCX questions | Upload valid DOCX | Questions created from DOCX |
| 6.15 | Import DOCX without Kategori/Pembahasan | Upload DOCX (optional fields omitted) | Questions created, no corrupted text |
| 6.16 | Import DOCX with empty Kategori/Pembahasan | Upload DOCX (fields present but empty) | Questions created cleanly |
| 6.17 | Download DOCX template | Click "Template Word" | File downloaded |
| 6.18 | Download CSV template | Click "Template CSV" | File downloaded |

### 7. Exam CRUD (`specs/admin/exams.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 7.1 | List exams | Visit `/admin/exams` | See exams with status |
| 7.2 | Create exam (basic) | Fill title, course, classroom, time, duration | Created, shown in list |
| 7.3 | Create exam with tab switch detection | Enable toggle, set max switches | Saved correctly |
| 7.4 | Create exam with fullscreen required | Enable toggle | Saved correctly |
| 7.5 | Create exam validation | Missing required fields | See validation errors |
| 7.6 | Edit exam | Change title, save | Updated |
| 7.7 | Toggle is_active | Toggle checkbox, save | Exam enabled/disabled |
| 7.8 | Delete exam | Click delete, confirm | Removed |

### 8. Exam Results & Monitoring (`specs/admin/exams.spec.ts` continued)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 8.1 | View exam results | Visit `/admin/exams/{id}/results` | See student scores table |
| 8.2 | Exam monitoring page | Visit `/admin/exams/{id}/monitor` | See real-time participant status |
| 8.3 | Student report card | Click student name in results | See per-question breakdown |
| 8.4 | PDF export | Click PDF button | PDF downloaded |
| 8.5 | CSV export results | Click CSV button | CSV downloaded with correct columns |
| 8.6 | Classroom recap | Visit recap | Matrix scores displayed |
| 8.7 | Classroom recap CSV | Click download | CSV downloaded |

### 9. Student Exam Flow (`specs/student/exam-flow.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 9.1 | Student dashboard | Login as student | See available exams |
| 9.2 | Exam show page | Click exam | See exam info + "Mulai Kerjakan" button |
| 9.3 | Start exam | Click "Mulai" | Navigate to attempt page, timer starts |
| 9.4 | Navigate questions | Click nav buttons | Switch between questions |
| 9.5 | Answer question via radio | Select option | Radio checked |
| 9.6 | Auto-save answer | Click radio, wait | POST `/save-answer`, answer persisted |
| 9.7 | Verify auto-save on reload | Reload page | Previously selected option still checked |
| 9.8 | Answered count updates | Answer multiple questions | Counter shows correct answered/total |
| 9.9 | Submission confirmation modal | Click "Kumpulkan" | Modal shows answered/total, warning if incomplete |
| 9.10 | Submit exam | Confirm submission | Redirect to result page, show score |
| 9.11 | Timer countdown | Wait | Timer decrements every second |
| 9.12 | Auto-submit on timer end | Wait for timer to reach 0 | Form auto-submits, score shown |
| 9.13 | Prevent re-entry after submission | Visit attempt URL again | Redirected, see "already finished" message |

### 10. Security Features (`specs/student/security.spec.ts`)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 10.1 | Fullscreen gate on load | Visit attempt page (not fullscreen) | Overlay appears, exam content blocked |
| 10.2 | Enter fullscreen | Click "Masuk Layar Penuh" | Overlay disappears, exam visible |
| 10.3 | Fullscreen exit increments counter | Exit fullscreen during exam | overlay reappears, tab_switches incremented |
| 10.4 | Tab switch detection | Switch to another tab (visibilitychange) | tab_switches counter incremented |
| 10.5 | Warning banner at threshold | Reach >=50% max_tab_switches | Red warning banner appears |
| 10.6 | Auto-submit on tab switch limit | Exceed max_tab_switches | Exam auto-submitted |
| 10.7 | Cannot re-enter after tab switch disqualification | Visit attempt URL | See "disqualified due to tab switches" |
| 10.8 | Backend guard: tab switch limit | Direct POST to `/tab-switch` exceeding limit | Returns `exceeded: true` |
| 10.9 | Backend guard: expired exam | Attempt expired exam | Access denied |
| 10.10 | Backend guard: inactive exam | Attempt deactivated exam | Access denied |
| 10.11 | Backend guard: wrong classroom | Student from other class accesses exam | 403/redirect |
| 10.12 | Save answer fails gracefully | Submit malformed answer data | No JS error, silent fail |

### 11. Edge Cases (`specs/student/exam-flow.spec.ts` continued)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 11.1 | Submit without answering all questions | Confirm submission with unanswered | Submission accepted, unanswered = wrong |
| 11.2 | Rapid click answers | Click multiple options quickly | All answered saved correctly (pending queue) |
| 11.3 | Browser back button during exam | Click browser back | Stay on attempt or see warning |
| 11.4 | Page refresh during exam | Refresh page | Timer continues, answers preserved |
| 11.5 | Exam with 0 questions | Start exam with no questions | See appropriate message |
| 11.6 | Remedial attempt | Fail exam, start remedial | New attempt allowed, attempt_number=2 |

### 12. Accessibility & UI (`specs/` as needed)

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| 12.1 | All pages have consistent nav | Visit each admin page | Same nav bar, active link highlighted |
| 12.2 | Responsive layout | Resize to mobile | Elements stack, no overflow |
| 12.3 | Flash messages display | Trigger success/error action | Message visible, dismissable |

---

## Running Tests

```bash
# One-time run
npx playwright test

# With UI mode
npx playwright test --ui

# Specific spec
npx playwright test specs/admin/exams.spec.ts

# With trace on failure
npx playwright test --trace on
```

---

## Notes

- Test DB: SQLite `:memory:` via `phpunit.xml` or separate MySQL `new_cbt_test`
- Seeder must create: 1 admin, 1 classroom, 1 course, 1 module, 5 questions, 1 exam, 2 students
- All admin tests must login as admin before running
- All student tests must login as student before running
- Use `beforeAll` / `afterAll` for seed data setup/teardown
- Tests should be independent (can run in any order)
- For PDF/CSV download tests, capture response headers + content-type
