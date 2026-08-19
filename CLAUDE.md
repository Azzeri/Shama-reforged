# Project conventions

## Language of code-level strings

All `__()` calls, translation keys, and any other in-code UI strings must be written in **English**, even though the app's content (recipes, ingredients) is in Polish. Do not hardcode literal Polish (or any other non-English) text as a translation key.

Do not add `resources/lang/*` translation files or switch `APP_LOCALE` unless explicitly asked to. Without a matching lang file, `__('Some string')` just echoes the English key back — that is the current, intended behavior. Don't try to fix that by building translation infrastructure on your own initiative.
