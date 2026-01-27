# Todo App mit Laravel & Flutter

Diese Anwendung ist eine umfassende Todo-App mit einem Laravel-Backend (Jetstream, Livewire, Tailwind CSS) und einer mobilen Flutter-App.

## Features

- **Web-Interface**: Erstellt mit Laravel Livewire und Tailwind CSS.
- **Mobile-App**: Flutter-Anwendung für iOS und Android.
- **Authentifizierung**: Jetstream (Web) und Sanctum API (Mobile).
- **Aufgabenverwaltung**:
  - Einmalige Aufgaben.
  - Wiederkehrende Aufgaben (Stündlich, Täglich, Wöchentlich, Monatlich).
  - Präzise Uhrzeiten für Aufgaben (wichtig für Push-Benachrichtigungen).
  - Fälligkeitsdaten und Beschreibungen.
- **API**: Vollständige REST-API für die mobile Integration.

## Aktueller Fokus
Der Fokus liegt derzeit auf dem **Laravel-Backend** und dem **Web-Interface**. Die mobile Flutter-App ist vorbereitet, wird aber in einem späteren Schritt finalisiert.

## Tech Stack

- **Backend**: Laravel 12, Jetstream, Sanctum.
- **Frontend Web**: Livewire 3, Tailwind CSS.
- **Mobile**: Flutter 3.
- **Datenbank**: MySQL/PostgreSQL (Standard Laravel).

### Firebase Cloud Messaging (FCM)

Das Projekt unterstützt Push-Benachrichtigungen via Firebase.

#### Mobile App Integration (WebView)
Wenn die App als WebView eingebunden wird (z. B. in Flutter), müssen das FCM-Token und die Geräte-ID bei jedem Request in den HTTP-Headern mitgesendet werden:

```dart
headers: {
  'X-FCM-Token': fcmToken,
  'X-Device-ID': deviceId,
}
```

Eine Laravel-Middleware (`HandleFcmTokenHeader`) erkennt diese Header automatisch und verknüpft das Gerät mit dem aktuell angemeldeten Benutzer.

#### API Integration
Für native API-Anfragen steht folgender Endpunkt zur Verfügung:
`POST /api/fcm-token` mit den Feldern `fcm_token` und `device_id` (optional).

#### Benachrichtigungen testen
Du kannst den Versand von Benachrichtigungen mit folgendem Artisan-Befehl testen:
```bash
php artisan fcm:test-notification {user_id}
```
Dieser Befehl sendet eine einfache Test-Push-Nachricht an alle registrierten Geräte des angegebenen Benutzers.

## Installation

### Backend (Laravel)

1. Repository klonen.
2. `composer install` ausführen.
3. `.env` Datei konfigurieren (Datenbank).
4. `php artisan migrate` ausführen.
5. `npm install && npm run dev` für das Frontend.
6. `php artisan serve` zum Starten.

### Mobile (Flutter) - *Folgt später*

1. In den Ordner `mobile` wechseln.
2. `flutter pub get` ausführen.
3. Die `baseUrl` in `lib/api_service.dart` anpassen (Standard ist `localhost`).
4. `flutter run` zum Starten.

## Datenmodell

- **Tasks**: Speichert die Aufgabendetails und die Wiederholungsregeln (`recurrence_rule`).
- **Task Completions**: Protokolliert die Erledigungen und Überspringen von Terminen.

## API Dokumentation

Die API nutzt Laravel Sanctum für die Authentifizierung. Alle Requests müssen den Header `Accept: application/json` enthalten.

### Authentifizierung

- `POST /api/login`: Login mit `email`, `password` und `device_name`. Gibt ein `token` zurück.
- `POST /api/logout`: Logout (erfordert Bearer Token).

### Aufgaben (Tasks)

- `GET /api/tasks`: Liste aller aktiven Basis-Aufgaben.
- `POST /api/tasks`: Neue Aufgabe erstellen.
  - **Felder**: `title` (string), `description` (text, nullable), `due_at` (datetime), `recurrence_rule` (JSON, optional).
  - **Recurrence Rule**: `{"frequency": "hourly|daily|weekly|monthly", "interval": 1, "times": ["HH:mm", ...], "weekdays": [1-7, ...]}`.
- `GET /api/tasks/{id}`: Details einer Aufgabe.
- `PUT /api/tasks/{id}`: Aufgabe aktualisieren (inkl. `is_active`, `is_archived`).
- `DELETE /api/tasks/{id}`: Gesamte Aufgabe/Serie löschen.

### Vorkommnisse (Occurrences) - Empfohlen für App-Liste

- `GET /api/tasks/occurrences`: Liste der expandierten Termine.
  - **Parameter**: `start`, `end` (Format: `YYYY-MM-DD HH:MM:SS`, Standard: nächste 7 Tage).
  - **Response**: Liste von Objekten mit `task` (Objekt), `planned_at` (datetime), `is_completed` (bool).

### Aktionen auf Vorkommnisse

- `POST /api/tasks/{id}/complete`: Ein Vorkommnis als erledigt markieren.
  - **Payload**: `{"planned_at": "YYYY-MM-DD HH:MM:SS"}` (optional, Standard ist der aktuelle `due_at`).
- `POST /api/tasks/{id}/skip`: Ein Vorkommnis überspringen (wird nicht mehr in der Liste angezeigt).
  - **Payload**: `{"planned_at": "YYYY-MM-DD HH:MM:SS"}` (erforderlich).

## Lizenz

MIT License
