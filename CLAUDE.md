# Project conventions

## Language of code-level strings

All `__()` calls, translation keys, and any other in-code UI strings must be written in **English**, even though the app's content (recipes, ingredients) is in Polish. Do not hardcode literal Polish (or any other non-English) text as a translation key.

The UI is being translated to Polish module by module (`APP_LOCALE=pl`): translations live in `lang/pl.json`, keyed by the English string. When you add or change a `__()` string in an already-translated module (currently: the shopping list, incl. ingredient categories and units), add its Polish translation to `lang/pl.json`. Plurals use the pipe form with three Polish forms, e.g. `":count przepis|:count przepisy|:count przepisów"` via `trans_choice()`. Modules not translated yet simply show the English key — don't translate them unless asked.

Dates shown to users should use `->locale(app()->getLocale())` rather than a hard-coded `locale('en')` in translated modules.
