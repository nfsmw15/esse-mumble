# esse-mumble Changelog

## 1.0.0 (2026-08-16)

Erste Veröffentlichung als esse-cms Plugin.

### Features
- Mumble-Server über einen oder mehrere **Mumble-Hosts** (via mumble-agent REST-API) verwalten
- Rollenbasierte **Quotas** für Member, Author und Editor (max. Server, max. Nutzer)
- **Host-Admin**-Rolle: delegierte Verwaltung einzelner Hosts ohne volle Admin-Rechte
- Admin-Oberfläche **Mumble Rechte** (`/admin/mumble-permissions`) zur Verwaltung
  plugin-spezifischer Berechtigungen und Host-Admin-Zuweisungen
- Konsolidierte Server-Übersicht (`/mumble/servers`) für Mitglieder, Host-Admins und Admins
  (zeigt je nach Rolle eigene, delegierte oder alle Server)
- Host-gefilterte Serverlisten und Host-Statistik-Seiten mit Live-Daten und Verlaufs-Charts
- Live-Dashboard mit Echtzeit-Statistiken (CPU, RAM, Bandbreite, Online-Nutzer)
- Statistik-Cron mit paralleler Datenabfrage (verhindert Timeouts bei vielen Servern)
  und konfigurierbarer Aufzeichnungsdauer (30 Tage)
- Channel-, Ban- und ACL-Verwaltung direkt im Admin-Bereich
- Öffentliches **Widget** und **Banner** (PNG) per Token-URL einbettbar
- Server-Import aus bestehendem Agent-Bestand
- SuperUser-Passwort-Verwaltung (Agent-generiert oder manuell)
- TLS-Zertifikat-Upload und -Entfernung über den Agent
- Audit-Log aller Server-Aktionen (inkl. User-ID und Erfolg)
- Assets werden sicher über eine PHP-Route ausgeliefert (kein direkter Verzeichniszugriff)
- Verschlüsselung sensibler Felder (Agent-Token, SuperUser-Passwort) via `Esse\Crypto`
- Versionsauswahl beim Server-Upgrade: Dropdown mit allen verfügbaren Mumble-Versionen
  (inkl. gezieltem Downgrade) statt Blind-Update auf ein festes Image; „Alle Server
  aktualisieren" auf der Host-Seite für einen Rolling-Update aller Server eines Hosts
  auf einmal — z.B. für kritische Patches. Setzt eine passende `mumble-agent`-Version
  mit `GET /v1/images` voraus; bei älteren Agents wird kein Dropdown angezeigt statt
  zu crashen.
- Pre-Release-Kennzeichnung: Versionen, die laut den GitHub-Releases von
  mumble-voip/mumble als Pre-Release markiert sind (z.B. v1.6.x), werden im Dropdown
  deutlich als „⚠ Pre-Release, nicht stabil" gekennzeichnet, ebenso am aktuell
  laufenden Image auf der Server-Seite.
- Host-Einstellung „Update-Kanal" (stable/prerelease): steuert serverseitig auf dem
  Agent, welche Versionen als „verfügbar" gemeldet werden — im stable-Kanal (Default)
  werden Pre-Releases komplett ausgeblendet, damit niemand versehentlich eine
  instabile Version installiert. Umstellen ist eine bewusste, seltene Aktion im
  Host-Bearbeiten-Formular (kein Live-Toggle im Dropdown), da sie einen kurzen
  Neustart (~3–4s) des Agent-Prozesses auslöst.
- Host-Übersicht zeigt jetzt die laufende `mumble-agent`-Version pro Host sowie,
  getrennt vom Mumble-Server-Update-Badge, ein eigenes Badge samt Ein-Klick-Button,
  falls für den Agent-Prozess selbst (Self-Updater ab Agent v2.15.0) eine neuere
  Version verfügbar ist — betrifft nur die Verwaltungs-API, nie die laufenden
  Mumble-Server-Container.

### Stabilität
- Selbstheilung bei veralteter Container-ID: Ging z.B. die Antwort eines Upgrades
  verloren (etwa weil der Agent währenddessen neu startete), kannte die DB nur noch
  die alte, nicht mehr existierende Container-ID — jede weitere Aktion lief dann mit
  „container not found" ins Leere. Schlägt eine Aktion jetzt mit HTTP 404 fehl, sucht
  das Plugin über das `external_id`-Label automatisch den aktuellen Container auf dem
  Agent, übernimmt dessen ID und versucht die Aktion einmal erneut. Zusätzlich gibt
  es einen manuellen „Server neu synchronisieren"-Button für Admins/Host-Admins.
- Ein nicht erreichbarer Mumble-Host konnte die komplette Website lahmlegen: PHP
  hält den Session-Lock für die gesamte Dauer eines hängenden Requests, wodurch ein
  einzelner hängender Agent-Aufruf alle weiteren Requests derselben Browser-Session
  blockierte, bis der PHP-FPM-Worker-Pool komplett voll war. Betroffene Endpunkte
  geben den Session-Lock jetzt vor Agent-Aufrufen frei.
- Öffentliche, unauthentifizierte Endpunkte (`/mumble/widget`, `/mumble/banner`)
  sowie das Live-Dashboard, die Host-Detailseite und mehrere Live-Endpunkte
  kontaktierten Agents auch dann, wenn deren Host bewusst deaktiviert war. Alle
  Live-Aufrufe prüfen jetzt `is_active` vorher und haben kurze, konsistente
  Timeouts (6–8s statt bis zu 30s).
- Der 5-Minuten-Statistik-Cron hatte keinen Überlappungsschutz — ein hängender Lauf
  ließ sich nicht mehr abschließen und jeder weitere Cron-Tick startete einen
  zusätzlichen parallelen Lauf. Läuft ein vorheriger Aufruf noch, bricht ein neuer
  jetzt sofort ab statt sich aufzustapeln.
- Der Server-Status blieb in der Datenbank auf „running" stehen, auch wenn der
  zugehörige Host dauerhaft nicht mehr erreichbar war. Cron und Live-Aktualisierung
  setzen den Status jetzt auf „error", sobald eine Live-Prüfung fehlschlägt.
