# SKR — Anonymous Encrypted Messenger

> A private messenger built on the principle that privacy is not a feature — it's the foundation.

No phone number. No email. No real name. Just a login, a keypair, and conversations that only you and your contact can read.

---

## What is SKR?

SKR is a real-time messaging application where **every message, file, photo, and location is end-to-end encrypted in the browser** before it ever leaves your device. The server stores only ciphertext — it has no ability to read your conversations, even in theory.

The social graph is equally private: you can only add people you actually know. There are no user directories, no search by username, no random friend requests. The only way to connect is through a **time-limited one-time friend code** that you share yourself.

---

## Core Features

### Cryptography
- **ECDH P-256 key exchange** — each user generates a keypair on registration; the public key is published to the server, the private key never leaves the browser
- **AES-256-GCM encryption** — every message, file chunk, photo, and location coordinate is encrypted with a key derived via ECDH + HKDF before being sent
- **Key backup** — private key can be exported encrypted with a user-chosen PIN and a 12-word recovery phrase, enabling secure cross-device use
- Zero server-side decryption — the server is structurally incapable of reading message content

### Friend System
- No user discovery — you cannot search for or stumble upon other users
- **10-digit one-time friend codes** — generated on demand, valid for 5 minutes, single-use
- QR code support for in-person code sharing
- The moment a code is used, it's invalidated

### Messaging
- Real-time delivery via **Laravel Reverb** (WebSockets)
- Read and delivered receipts
- Typing indicators
- Emoji picker
- Message history with lazy loading

### File & Photo Sharing
- Files and photos are encrypted chunk-by-chunk before upload
- Photos are automatically compressed (Canvas API, max 1280px, JPEG 0.82) to save bandwidth while preserving the original for download
- Fullscreen photo viewer with **pan & zoom** (wheel zoom toward cursor, drag to pan, pinch-to-zoom on mobile)
- Files rendered with type-specific previews

### Live Location Sharing
- Share your real-time location for 5 / 15 / 30 / 60 / 180 minutes
- GPS coordinates are **encrypted client-side** before each position update — the server only ever stores the last encrypted payload
- Embedded Leaflet.js map tile in the chat bubble; tap to open fullscreen
- One-time location sharing also available
- Live session stops automatically when the timer expires or the sender ends it

### Notifications
- **Web Push notifications** via VAPID — works even when the tab is closed
- In-app sound and toast notifications for incoming messages

---

## Tech Stack
| Layer         | Technology
| Backend       | PHP 8.4, Laravel 13 
| WebSockets    | Laravel Reverb 
| Database      | PostgreSQL@17
| Frontend      | TypeScript, Vite, SCSS, Tailwind CSS 4 
| Cryptography  | Web Crypto API (browser-native, zero dependencies) 
| Maps          | Leaflet.js + OpenStreetMap 
| Push          | Web Push (VAPID) via `minishlink/web-push` 
| QR Codes      | `simplesoftwareio/simple-qrcode` 

---

## Architecture Highlights

The frontend is a fully typed TypeScript application split into focused modules (`crypto.ts`, `keys.ts`, `location.ts`, `location-map.ts`, `file-upload.ts`, `file-display.ts`, `messages.ts`, `websocket.ts`, ...) bundled by Vite. There is no framework — just clean, modular ES modules with a clear separation of concerns.

The encryption layer uses only the **Web Crypto API** — a browser-native, audited implementation. No third-party crypto library is needed or trusted. Key derivation follows the ECDH → HKDF → AES-256-GCM chain, with a fresh IV for every encrypt call.

The backend follows standard Laravel conventions: Eloquent models, route-model binding, form requests for validation, broadcast events for real-time, and synchronous queue (`QUEUE_CONNECTION=sync`) to keep the stack simple without sacrificing real-time behavior.

---

## Why Laravel?

I chose Laravel because **I know it deeply** — not just how to use it, but how it works under the hood. I test my own code, write feature tests, refactor toward correctness, and maintain the codebase with the same rigor I'd apply to production systems.

Laravel 13 gives exactly what this project needs: a solid HTTP layer, first-class WebSocket support via Reverb, expressive Eloquent, and a mature ecosystem. I'm not fighting the framework — I'm using it the way it was designed to be used.

---

## Built with AI Assistance

This project was built with the help of **Claude (Anthropic)** (future maybe Codex) as a coding collaborator. AI was used to accelerate implementation, explore architectural options, and catch edge cases — but every design decision, architectural choice, and line of production code was reviewed, understood, and owned by me.

This is what modern engineering looks like: a skilled developer who understands their tools deeply, uses AI to move faster, and takes full responsibility for the output. The AI doesn't replace understanding — it amplifies it.

The project also includes a Claude Agent SDK integration (`agent/`) for automated tasks against the codebase.

---

## Running Locally

Requires PHP 8.3+, Composer, Node.js 20+.

```bash
git clone <repo>
cd skr
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Start all three servers concurrently:

```bash
composer dev
```

Or individually:

```bash
php artisan serve          # HTTP  → localhost:8000
php artisan reverb:start   # WS    → localhost:8080
npm run dev                # Vite  → localhost:5173
```

---

## Credits

SKR was originally created by Airat (@Ayra47).

---

## License

Copyright (c) 2026 SKR Contributors

Licensed under the GNU Affero General Public License v3.0 (AGPLv3).

See the LICENSE file for details.