# AI Agent Instructions

## Project overview
- This workspace holds a school library management project described in `biblioteca.md`.
- The system is intended as a web-based library application for students, teachers, librarians, administrators, and public visitors.
- Core functionality includes book management, user management, loans, returns, reservations, reports, and login/access control.

## Current workspace state
- Only `biblioteca.md` exists today; no source code or framework scaffolding is present.
- Assume this is a new or early-stage PHP/HTML/CSS/JavaScript project targeting an XAMPP-style environment.

## Technical guidance
- Prefer plain PHP for backend implementation unless the user explicitly asks for a specific framework.
- Use standard HTML/CSS/JavaScript for the frontend UI.
- Support MySQL or SQLite for persistence; database schema should match the requirements in `biblioteca.md`.
- Keep architecture simple and easy to run locally.

## Key features to support
- Authentication and session-based access control.
- Role-based user management: administrator, librarian, student, visitor.
- Book CRUD with inventory tracking.
- Loan/return workflows, due-date checks, overdue handling, and simple blocking rules.
- Reservation queue management.
- Reports for active loans, overdue books, stock, and users.

## Agent behavior
- If asked to build or improve the app, base the design on the feature list in `biblioteca.md`.
- Link to `biblioteca.md` for requirements instead of duplicating large sections.
- Do not assume existing frontend or backend frameworks unless they are added later.
- When adding files, keep naming and structure conventional for PHP web apps.

## Notes for contributors
- Since there is no existing source tree, start with minimal scaffolding and iterate from the documented requirements.
- Avoid over-engineering; a straightforward implementation is preferred in this workspace.

## Recent implementation notes
- The interface now uses a more polished visual style with improved typography, spacing, headers, cards, buttons, and table readability.
- Shared CSS was updated to improve contrast and readability in both light and dark themes.
- Dark mode was specifically refined for registration, reservations, and general form content so labels, inputs, and messages remain clearly visible.
- The admin dashboard now includes a reading overview section showing the most-read and least-read books based on loan data.
- The student dashboard now supports profile photo updates with default avatar choices for feminine, masculine, and library-themed visuals.
- The main UI files involved in these changes are [css/style.css](css/style.css), [includes/header.php](includes/header.php), [reservations.php](reservations.php), [register.php](register.php), [student_dashboard.php](student_dashboard.php), and [includes/auth.php](includes/auth.php).

## teste
