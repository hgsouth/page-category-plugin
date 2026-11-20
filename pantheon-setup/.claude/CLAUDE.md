# CLAUDE.md — canine site (Pantheon), Hayley's Multidev

This folder is a local clone of the Pantheon git repo for the **canine** WordPress site.
I (Hayley) work only on the **`hayley`** branch / Multidev. An agency (Cornershop Creative)
maintains `master`, Dev/Test/Live, and runs Pantheon Autopilot updates. Stay out of their way.

## Hard rules

1. **Branch:** only ever commit to `hayley`. Never commit to, merge into, rebase onto, or push
   `master` or any other branch. If `git status` shows any other branch, stop and tell me.
2. **No force pushes.** No `git push --force`, no `git reset --hard` on pushed commits, no history rewrites.
3. **Ask before every `git push`.** Show me `git log origin/hayley..HEAD --oneline` and the diff summary first.
4. **Environments:** the only site you may contact is the Multidev:
   `https://hayley-canine.pantheonsite.io`
   Never send requests to Dev (`dev-canine…`), Test (`test-canine…`), Live (`live-canine…`) or the
   public domain. Merges and deploys to Dev/Test/Live are done by me in the Pantheon dashboard, never by you.
5. **Private data:** never try to read Gravity Forms entries, user lists/emails, comments' personal
   data, database dumps or backups. Don't call `/wp-json/gf/…` or `/wp-json/wp/v2/users` or `/comments`.
   If a task seems to need that data, stop and ask.
6. **Secrets:** never print, read or copy the WordPress credential file, SSH keys, or tokens.
   The page scripts load the credential themselves.
7. **Don't touch WordPress core or Autopilot-managed plugins** (third-party plugins in
   `wp-content/plugins/` that Autopilot updates). Only edit our theme, our own plugins, and files I name.
   Never run plugin/core updates.

## Editing code (theme / plugins)

- Make changes, show me the diff, explain what changed and why.
- Before proposing a push, review the change against `.claude/review-checklist.md`
  (WordPress security/standards checklist) and report anything that fails.
- After I approve, commit with a clear message and (after I approve again) `git push origin hayley`.
  The Multidev updates within a minute; I'll check it at https://hayley-canine.pantheonsite.io.

## Editing page content (Gutenberg)

Page content lives in the database, not in git. Use the PowerShell scripts in `.claude/scripts/`,
which talk to the Multidev's REST API with a limited Editor-role Application Password:

```powershell
# list pages (id, status, slug, title)
powershell -ExecutionPolicy Bypass -File .claude/scripts/pull-page.ps1 -List

# download a page's block markup to .claude/pages/<id>-<slug>.html
powershell -ExecutionPolicy Bypass -File .claude/scripts/pull-page.ps1 -Id 123

# upload edited markup back to the Multidev
powershell -ExecutionPolicy Bypass -File .claude/scripts/push-page.ps1 -Id 123
```

- Always `pull-page` immediately before editing, so you work from the latest version.
- Keep valid block markup: every `<!-- wp:name {...} -->` needs its matching `<!-- /wp:name -->`,
  and block attributes in the comment must match the HTML inside (otherwise the editor shows
  "This block contains unexpected or invalid content").
- Ask me before every `push-page`. After pushing, tell me the page URL so I can check it in the editor.
- Edits on the Multidev do **not** flow to Live. When I'm happy with a page, I copy it to Live myself.
- Use `-Type posts` for posts instead of pages.
