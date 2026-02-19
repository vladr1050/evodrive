# MCP Server Usage in EvoDrive Development

This document describes how MCP (Model Context Protocol) servers were used during the implementation of EvoDrive.lv.

## Configured MCP Servers

### 1. server-filesystem (mcp_filesystem_*)

**Purpose:** File system operations for creating, reading, updating, and moving files.

**Usage during implementation:**
- `mcp_filesystem_directory_tree` – Explored project structure, design folder layout, and Laravel app directory
- `mcp_filesystem_list_directory` – Listed migrations, views, and resource directories
- `mcp_filesystem_create_directory` – Created `lang/en`, `lang/ru`, `lang/lv`, `resources/views/landing`, `resources/views/apply`, etc.
- `mcp_filesystem_write_file` / `mcp_filesystem_edit_file` – Created and updated migrations, models, controllers, views, seeders, and config files
- `mcp_filesystem_search_files` – Located files by pattern (e.g. `**/lang/**/*.php`)
- `mcp_filesystem_read_multiple_files` – Batch read of design reference files (GoogleLanding.tsx, MetaLanding.tsx, ApplyFlow.tsx, data.ts)

### 2. server-git (mcp_github_*)

**Purpose:** Git and GitHub operations for version control and collaboration.

**Usage during implementation:**
- Not used in initial implementation (project is not a git repo yet). Available for:
  - Commits and diffs
  - Branch management
  - Pull request creation
  - Issue tracking

### 3. server-fetch (mcp_web_fetch)

**Purpose:** Fetching external documentation and URLs.

**Usage during implementation:**
- Could be used to fetch Laravel/Filament documentation
- Could fetch Tailwind CSS docs for layout patterns
- Available for documentation lookup during development

## Recommended MCP Usage Workflow

1. **Project setup:** Use filesystem MCP to create directory structure, migrations, and base files.
2. **Schema review:** Use filesystem MCP to read migration files and verify DB schema.
3. **Search references:** Use filesystem `search_files` or project `Grep` to find usages of models, routes, and components.
4. **Documentation:** Use web fetch MCP for Laravel, Filament, or Tailwind docs when needed.
5. **Version control:** Use GitHub MCP for commits and PRs once the project is under git.

## Optional MCP Servers

- **server-postgres / DB MCP:** If available, for direct database schema review and query execution. EvoDrive uses SQLite by default; PostgreSQL can be configured via `.env`.
- **server-fetch:** For real-time documentation lookup during implementation.

## File Operations Summary

The filesystem MCP was used to:
- Create 8 migration files
- Create 4 models (SiteSetting, Page, PageSection, Lead)
- Create 2 controllers (LandingController, ApplyController)
- Create middleware (SetLocale)
- Create 6 lang files (ui.php, apply.php for en/ru/lv)
- Create Blade layouts, components, and views
- Create Filament resources (SiteSettingResource, LeadResource)
- Create database seeders
