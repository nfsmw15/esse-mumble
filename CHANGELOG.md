# esse-mumble Changelog

## 1.0.0 (2026-06-07)

Erste stabile Veröffentlichung als esse-cms Plugin.

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
