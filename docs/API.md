# API REST v1

Base: `http://<servidor>/api/v1` · Siempre responde **JSON** · Límite: 240 pedidos/minuto por token
(balanza: 300/min).

## Autenticación

1. Administración → **Tokens de API** → Nuevo token. El super administrador puede crearlo a nombre de un
   usuario técnico (recomendado: un usuario «balanza1», «sensores», etc. con el rol mínimo necesario).
2. Elegir las habilidades: `read` (consultas), `scale:write` (balanzas), `sensors:write` (sensores).
3. Copiar el token: **se muestra una sola vez**. Si se pierde, revocarlo y crear otro.
4. Enviarlo en cada pedido:

```http
Authorization: Bearer 12|AbCdEf...
Accept: application/json
```

Un token **nunca puede más que su usuario**: además de la habilidad, se exige el permiso del usuario y
que el módulo esté activo. Si el usuario se da de baja, sus tokens dejan de funcionar.

| Código | Significado |
|---|---|
| 401 | Token faltante, inválido, vencido o revocado |
| 403 | El token no tiene la habilidad, o el usuario no tiene el permiso / está inactivo |
| 404 | No existe el registro o el módulo está desactivado |
| 422 | Datos inválidos: `{"message": "...", "errors": {"campo": ["..."]}}` |
| 429 | Demasiados pedidos: esperar y reintentar |

## Consultas (`read`)

| Método y ruta | Permiso del usuario | Descripción |
|---|---|---|
| `GET /me` | — | Usuario y habilidades del token |
| `GET /cajones?status=&code=&updated_since=&per_page=` | crates.view | Cajones paginados (máx. 100 por página) |
| `GET /cajones/{codigo}` | crates.view | Un cajón |
| `GET /pallets/{codigo}` | pallets.view | Un pallet con cantidad de cajones |
| `GET /cargas?status=&from=&to=` | loads.view | Cargas paginadas |
| `GET /cargas/{numero}` | loads.view | Una carga con la lista de cajones |
| `GET /insumos` | supplies.view | Stock de insumos y si está bajo |
| `GET /produccion/hoy` | dashboard.view | Indicadores del día |

Ejemplo:

```bash
curl -H "Authorization: Bearer $TOKEN" http://192.168.1.100/api/v1/cajones/CJ-000123
```

```json
{"data": {"code": "CJ-000123", "status": "processed", "quality_status": "pending", "weight_kg": 18.5,
  "variety": "Naranja Valencia", "size": "Tamaño 70", "packer": "EMB001", "pallet": "PAL-000045",
  "processed_at": "2026-10-01T16:19:00-03:00", "updated_at": "2026-10-01T16:19:00-03:00"}}
```

Las listas devuelven `{"data": [...], "meta": {"current_page", "per_page", "total", "last_page"}}`.
Para sincronizar, usar `updated_since` con la fecha del último pedido.

## Balanza (`scale:write`, permiso production.scan)

Un pequeño programa en la PC de la balanza envía cada lectura estable; el **modo escaneo** la toma
automáticamente cuando en Configuración → Producción la balanza está en modo «API».

```http
POST /api/v1/balanza/lecturas
{"station": "linea-1", "weight": 18.45, "unit": "kg", "stable": true}
```

- `station`: identificador del puesto (letras, números, `.`, `_`, `-`).
- `unit`: `kg` (por defecto) o `g`. Máximo 5000 kg.
- Respuesta `201` con la lectura guardada. Una lectura de más de 10 segundos se informa como desactualizada.

## Sensores de cámaras (`sensors:write`, permiso cold_rooms.manage)

```http
POST /api/v1/sensores/lecturas
{"sensor_key": "SENS-01", "temperature": 2.5, "humidity": 90, "recorded_at": "2026-10-01T10:00:00-03:00"}
```

- `sensor_key`: el configurado en la cámara frigorífica.
- `temperature` entre -60 y 60 °C; `humidity` 0–100 %; `recorded_at` opcional (hasta 2 días atrás).
- Si la lectura está fuera del rango de la cámara se genera una **alerta crítica** y la respuesta trae
  `"out_of_range": true`.

## Latido

`GET /api/heartbeat` (sin token): `{"ok": true, "time": "..."}`. Lo usan las pantallas para detectar
cortes de conexión.
