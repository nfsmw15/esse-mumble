# esse-mumble

Mumble-Server Verwaltung als Plugin für [esse-cms](https://github.com/nfsmw15/esse-cms).

[![Release](https://img.shields.io/github/v/release/nfsmw15/esse-mumble?label=release&color=blue)](https://github.com/nfsmw15/esse-mumble/releases)
[![License](https://img.shields.io/badge/license-AGPL--3.0--or--later-green)](LICENSE)
[![ESSE CMS](https://img.shields.io/badge/esse--cms-%3E%3D0.1.0-orange)](https://github.com/nfsmw15/esse-cms)

## Über das Plugin

esse-mumble integriert die Verwaltung von Mumble-Voice-Servern direkt in das
esse-cms-Backend. Über die REST-API von [mumble-agent](https://github.com/nfsmw15/mumble-agent)
lassen sich Server auf einem oder mehreren Hosts anlegen, konfigurieren,
überwachen und mit anderen Nutzern teilen — inklusive rollenbasierter Quotas,
delegierter Host-Administration durch Host-Admins und öffentlich einbettbarer
Widgets bzw. Banner.

## Voraussetzungen

- esse-cms >= 0.1.0
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- [mumble-agent](https://github.com/nfsmw15/mumble-agent) auf jedem Mumble-Host

## Installation

1. Dieses Repository als ZIP herunterladen oder über den esse Plugin-Browser installieren
2. ZIP in `plugins/esse-mumble/` entpacken
3. Plugin im Admin-Bereich unter **Plugins** aktivieren

Das Plugin legt beim ersten Start alle benötigten Datenbanktabellen automatisch an (`boot()` → `CREATE TABLE IF NOT EXISTS`).

## Routen

| Route | Beschreibung | Sichtbarkeit |
|---|---|---|
| `/mumble/servers` | Konsolidierte Server-Übersicht (eigene / delegierte / alle, je nach Rolle) | Mitglieder |
| `/mumble/new` | Neuen Server anlegen | Mitglieder |
| `/mumble/edit/{id}` | Server bearbeiten | Mitglieder |
| `/mumble/config/{id}` | Server-Konfiguration | Mitglieder |
| `/mumble/logs/{id}` | Server-Logs | Mitglieder |
| `/mumble/channels/{id}` | Channel-Verwaltung | Mitglieder |
| `/mumble/bans/{id}` | Ban-Verwaltung | Mitglieder |
| `/mumble/acl/{id}` | ACL-Verwaltung | Mitglieder |
| `/mumble/server-stats/{id}` | Server-Statistiken mit Verlaufs-Charts | Mitglieder |
| `/mumble/widget-settings/{id}` | Widget-/Banner-Einstellungen (Token, Sichtbarkeit) | Mitglieder |
| `/mumble/host-stats/{id}` | Host-Statistiken mit Live-Daten und Verlauf | Mitglieder |
| `/mumble/host-detail/{id}` | Host-gefilterte Serverliste | Mitglieder |
| `/mumble/hosts` | Hosts anlegen/bearbeiten, Cron-Key verwalten | Mitglieder (Admin/Host-Admin) |
| `/mumble/quota` | Rollenbasierte Quotas bearbeiten | Mitglieder (Admin) |
| `/mumble/dashboard` | Live-Dashboard mit Echtzeit-Statistiken | Mitglieder (Admin/Host-Admin) |
| `/mumble/widget` | Einbettbares Server-Widget per Token | öffentlich |
| `/mumble/banner` | Einbettbares Server-Banner (PNG) per Token | öffentlich |
| `/mumble/cron/collect` | Statistik-Cron-Endpunkt (Cron-Key-geschützt) | öffentlich |
| `/mumble/api/*` | AJAX-Endpunkte für UI-Interaktionen (Stats, Dashboard, Channels, Bans, ACL, Live-Nutzer …) | öffentlich (Session-Auth) |
| `/admin/mumble-permissions` | Admin-Oberfläche „Mumble Rechte" (Berechtigungen, Host-Admin-Zuordnung) | Admin |

## Berechtigungen

Das Plugin registriert folgende Berechtigungen in esse:

| Slug | Bedeutung |
|---|---|
| `mumble_view` | Mumble-Serverübersicht ansehen |
| `mumble_admin` | Alle Mumble-Server verwalten |
| `mumble_hosts` | Hosts anlegen/bearbeiten |
| `mumble_quota` | Quota-Regeln bearbeiten |
| `mumble_host_admin` | Delegierte Host-Verwaltung |

Diese Berechtigungen lassen sich pro Nutzer im Admin-Bereich unter
**Mumble Rechte** (`/admin/mumble-permissions`) vergeben — inklusive der
Zuordnung von Host-Admins zu einzelnen Hosts.

## Features

- Verwaltung mehrerer Mumble-Hosts (via [mumble-agent](https://github.com/nfsmw15/mumble-agent) REST-API)
- Rollenbasierte Quotas für Nutzer (Member / Author / Editor)
- Host-Admin-Rolle für delegierte Host-Verwaltung ohne volle Admin-Rechte
- Admin-Oberfläche zur Verwaltung plugin-spezifischer Berechtigungen und Host-Admin-Zuweisungen
- Konsolidierte Server-Übersicht je nach Rolle (eigene / delegierte / alle Server)
- Live-Dashboard: CPU, RAM, Bandbreite, Online-Nutzer in Echtzeit
- Statistik-Verlauf (24h / 7d / 30d) mit Chart.js
- Channel-, Ban- und ACL-Verwaltung
- Öffentliches Widget und Banner (PNG) per Token einbettbar
- Server-Import aus bestehendem Agent-Bestand
- Audit-Log aller Aktionen

## Konfiguration

Nach der Aktivierung im Admin-Bereich unter **Mumble → Hosts** mindestens einen Mumble-Host anlegen:

| Feld | Bedeutung |
|---|---|
| Name | Anzeigename des Hosts |
| Hostname | Öffentlicher Hostname (für Clients) |
| Agent-URL | HTTP/HTTPS-URL des mumble-agent, z.B. `https://mumble1.example.com:8080` |
| Agent-Token | API-Token des mumble-agent |
| Port-Bereich | Min/Max-Port für neue Server (Standard: 64738–64838) |

### Statistik-Cron

Für die Statistik-Aufzeichnung (CPU, RAM, Nutzer) einen Cronjob einrichten:

```
*/5 * * * * curl -s "https://example.com/mumble/cron/collect?key=DEIN_CRON_KEY" > /dev/null
```

Den Cron-Key unter **Mumble → Hosts verwalten** generieren und kopieren.
Die Datenabfrage läuft parallel über alle Hosts/Server, sodass der Aufruf
auch bei größeren Installationen innerhalb weniger Sekunden abgeschlossen ist.

## Datenbankstruktur

| Tabelle | Zweck |
|---|---|
| `mumble_host` | Mumble-Hosts (Agent-URL, Agent-Token, Port-Bereich, Quotas) |
| `mumble_server` | Mumble-Server (Owner, Container-ID, Status, Widget-Token, SuperUser-Passwort) |
| `mumble_server_members` | Zuordnung von Mitgliedern zu Servern |
| `mumble_log` | Audit-Log aller Server-Aktionen (User-ID, Erfolg) |
| `mumble_quota` | Rollenbasierte Quotas (max. Server, max. Nutzer) |
| `mumble_stats` | Statistik-Verlauf (CPU, RAM, Bandbreite, Online-Nutzer je Server) |
| `mumble_settings` | Plugin-Einstellungen (Key/Value) |
| `mumble_host_admin` | Zuordnung von Host-Admins zu einzelnen Hosts |

## Dateistruktur

```
esse-mumble/
├── plugin.json
├── Plugin.php
├── MumbleAgent.php          ← REST-Client für mumble-agent (inkl. paralleler Dashboard-Abfrage)
├── MumbleRepository.php     ← DB-Zugriff, Migrationen, Berechtigungen
├── admin/
│   └── permissions.php      ← Admin-Oberfläche „Mumble Rechte"
├── assets/                  ← Plugin-eigenes JS (Dashboard, Charts, Hosts, Embed, …)
├── frontend/                ← öffentliche Seiten (Widget, Banner, Cron-Endpunkt)
├── templates/               ← Mitglieder-/Admin-Templates
├── README.md
├── CHANGELOG.md
└── LICENSE
```

## Sicherheit

- Sensible Felder (Agent-Token, SuperUser-Passwort) werden über `Esse\Crypto` verschlüsselt in der Datenbank gespeichert
- Plugin-Assets werden über eine eigene PHP-Route ausgeliefert — kein direkter Verzeichniszugriff
- Öffentliche Endpunkte (Widget, Banner, Statistik-Cron) sind durch individuelle Tokens bzw. einen Cron-Key geschützt

## Lizenz

AGPL-3.0-or-later — siehe [LICENSE](LICENSE)
