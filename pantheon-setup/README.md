# Claude Code setup for the canine Pantheon site (hayley Multidev)

Copy the **`.claude` folder** from here into the root of your local Pantheon clone
(the `canine` folder), so you end up with `canine\.claude\CLAUDE.md`, `canine\.claude\settings.json`, etc.

## 1. Keep these files out of git (so they never deploy to the website)

In VS Code's PowerShell terminal, inside the `canine` folder:

```powershell
Add-Content .git\info\exclude ".claude/"
```

`git status` should now **not** list `.claude/`. (`.git/info/exclude` is local to your computer,
so this doesn't change anything for Cornershop.)

## 2. Allow the scripts to run

```powershell
Get-ChildItem .claude\scripts\*.ps1 | Unblock-File
```

## 3. Create the Application Password (in the Multidev's WP admin)

1. Open https://hayley-canine.pantheonsite.io/wp-admin
2. Users → User Role Editor: check the **Editor** role has no `gravityforms_*`, `list_users`,
   `edit_users` (and optionally no `moderate_comments`).
3. Create a user with the Editor role (or use an existing Editor), then on its profile:
   Application Passwords → name it "Claude" → Add. Copy the password shown.

## 4. Save the credential (run this yourself, not through Claude)

```powershell
powershell -ExecutionPolicy Bypass -File .claude\scripts\save-wp-credential.ps1
```

Username = the Editor's username; password = the Application Password.

## 5. Test

```powershell
powershell -ExecutionPolicy Bypass -File .claude\scripts\pull-page.ps1 -List
```

You should see a list of pages. Then open Claude Code in VS Code in the `canine` folder.
