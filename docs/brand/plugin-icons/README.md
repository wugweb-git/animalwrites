# Kits plugin icons

One icon per product: Kits (the suite), Kits Core, RankWeb, InsightWeb, SourceWeb, MailWeb, DirectoryWeb and PlanWeb.

Each icon comes as `<name>.svg`, `<name>-256.png` and `<name>-128.png`.

Plugins that exist today carry their own copy in `assets/`, named the way WordPress expects:

| Plugin | Folder | Files |
|---|---|---|
| Kits Core | `plugins/kits-core/assets/` | `icon.svg`, `icon-256x256.png`, `icon-128x128.png` |
| RankWeb | `plugins/aw-seo-extras/assets/` | same |
| SourceWeb | `plugins/aw-sourcing/assets/` | same |
| MailWeb | `plugins/mailweb/assets/` | same |

InsightWeb, DirectoryWeb and PlanWeb copy theirs from here into their own `assets/` when they are built. The Kits Core admin shell shows each plugin's icon on the Kits → Plugins row and in the app header.
