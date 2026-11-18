# Deploy (CI/CD)

Push na `main` → GitHub Actions uruchamia testy listy zakupów na swoich
serwerach → jeśli przejdą, **self-hosted runner na domowym serwerze** robi
`git reset --hard origin/main` w katalogu aplikacji i odpala
`.deploy/deploy.sh`, a na końcu sprawdza `/up`.

Workflow: `.github/workflows/deploy.yml`. Deploy można też odpalić ręcznie:
*Actions → deploy → Run workflow*.

## Jednorazowa konfiguracja

### 1. Runner na serwerze

GitHub → repo → *Settings → Actions → Runners → New self-hosted runner →
Linux*. Wykonaj podane tam komendy jako użytkownik, który jest właścicielem
katalogu aplikacji. Przy `config.sh` dodaj etykietę `home-server`:

```bash
mkdir ~/actions-runner && cd ~/actions-runner
# ...komendy pobrania (curl + tar) skopiowane z GitHuba...
./config.sh --url https://github.com/Azzeri/Shama-reforged --token <TOKEN> --labels home-server
sudo ./svc.sh install
sudo ./svc.sh start
```

Runner łączy się z GitHubem od środka — nie trzeba otwierać żadnych portów.

### 2. sudo bez hasła dla deployu

`deploy.sh` używa `sudo` (docker, chown, chmod). Sprawdź ścieżki (`which docker chown chmod`)
i dodaj plik przez `sudo visudo -f /etc/sudoers.d/shama-deploy`:

```
<user-runnera> ALL=(root) NOPASSWD: /usr/bin/docker, /usr/bin/chown, /usr/bin/chmod
```

### 3. Zmienne w repo

*Settings → Secrets and variables → Actions → Variables*:

| Zmienna | Wartość |
|---|---|
| `APP_DIR` | pełna ścieżka do katalogu aplikacji na serwerze (tam, gdzie jest `.git` i `.env`) |
| `HEALTHCHECK_URL` | opcjonalnie; domyślnie `http://localhost/up` |

Katalog aplikacji musi móc zrobić `git fetch` z GitHuba (jak dotąd przy
ręcznym `git pull`).

### 4. Bezpieczeństwo

- Jeśli repo jest publiczne: *Settings → Actions → General → Fork pull request
  workflows* → wymagaj zatwierdzenia dla wszystkich zewnętrznych osób.
  Deploy odpala się tylko przy pushu na `main` / ręcznie, ale runner ma
  dostęp do serwera.
- W *Settings → Environments → production* można dodać wymóg ręcznego
  zatwierdzenia każdego deployu.

## Co robi `deploy.sh`

1. `docker compose up -d` (kontenery muszą działać)
2. katalogi `storage/*`, `public/build`
3. `composer install`
4. build frontendu w jednorazowym kontenerze `node:20`
5. uprawnienia
6. **tryb serwisowy** (`artisan down`) — tylko na migracje i cache, wyłączany
   zawsze na końcu, także po błędzie
7. `artisan migrate --force` — **przed większymi migracjami zrób backup bazy**
8. `optimize:clear`, `optimize`, `view:cache`

Uwaga: `git reset --hard` nadpisuje na serwerze ręczne zmiany w plikach
śledzonych przez gita. `.env`, `storage/` i `vendor/` są poza gitem, więc
zostają.
