# Teltonika gateway (EvoDrive)

TCP listener for Teltonika FMC devices (IMEI handshake, Codec8 AVL ACK, Codec12 GPRS commands) plus an **internal HTTP API** consumed by Laravel `GprsCarDeviceTransport`.

## Listen ports

| Port | Purpose |
|------|---------|
| 5528 | Devices (TCP). В Teltonika это **Server 2** (второй сервер): сюда должен указывать IP/порт этого gateway. |
| 8080 | HTTP API (internal only; bind to Docker network / VPN). |

### EvoDrive работает только с Server 2

- **Laravel + `teltonika-gateway`** принимают команды и считают «online» **только** по TCP-сессии, которая приходит **на этот процесс** — в конфигурации FMC130 это слот **Server 2**.
- **Server 1** для EvoDrive **не используется**: мы не интегрируемся с тем хостом, не читаем его сессии и не отправляем туда команды. Он нужен вам отдельно (другой оператор / дублирование телематики и т.д.).
- Режим **Duplicate** на трекере имеет смысл, если параллельно нужен Server 1 (вне EvoDrive) и Server 2 (наш gateway). Если нужен только наш канал — заполняйте **только Server 2** по правилам прошивки для вашей модели (или используйте Duplicate с заполненным Server 1 по вашей политике).

## Environment

| Variable | Default | Description |
|----------|---------|-------------|
| `GATEWAY_TCP_ADDR` | `:5528` | Teltonika TCP bind address |
| `GATEWAY_HTTP_ADDR` | `:8080` | HTTP bind address |
| `GATEWAY_HTTP_TOKEN` | _(empty)_ | If set, require `Authorization: Bearer <token>` on HTTP |
| `GATEWAY_ONLINE_TTL_SECONDS` | `90` | Device considered online if last AVL/Codec12 frame within TTL |

## HTTP API (matches Laravel `config/car_control.php` → `gprs`)

### `POST /commands`

```json
{
  "imei": "123456789012345",
  "command": "youto youto lvcanopenalldoors",
  "timeout_seconds": 30
}
```

- **200** `{ "ok": true, "response": "<device Codec12 response text>" }`
- **404** `{ "ok": false, "failure_code": "device_offline" }` — no active TCP session for IMEI
- **408** `{ "ok": false, "failure_code": "timeout" }`
- **502** `{ "ok": false, "failure_code": "connection_lost" }` — write/socket error

### `GET /devices/{imei}/status`

```json
{
  "online": true,
  "status": "online",
  "last_seen_at": "2026-04-22T12:00:00.000000000Z",
  "imei": "123456789012345"
}
```

Laravel expects `online: true` **or** `status: "online"` — both are set when applicable.

## Laravel

Set in `.env`:

```env
CAR_CONTROL_TRANSPORT=gprs
CAR_CONTROL_GPRS_INTERNAL_BASE_URL=http://teltonika-gateway:8080
CAR_CONTROL_GPRS_INTERNAL_TOKEN=same-as-GATEWAY_HTTP_TOKEN
```

## Limits (MVP)

- TCP AVL framing: supports both **4-byte zero preamble + length** and **length-first** (no leading zeros), as seen on some FMB/FMC firmwares.
- One TCP session per IMEI; new connection replaces the old one.
- At most **one Codec12 command in flight** per session (matches fleet spec).
- Codec8 ACK uses **Number of Data 1** as accepted count (sufficient for typical single-batch packets; extend if you see multi-batch issues).

## Build

```bash
go build -o teltonika-gateway ./cmd/gateway
```

Docker: see repository `docker-compose.yml` service `teltonika-gateway`.
