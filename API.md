# Aktau City Lab — API

REST API для фронтенда. Все ответы — JSON без обёртки `data`. Примеры сняты с реально работающего API на демо-данных.

- **Base URL:** локально `http://localhost:8091/api` (Docker), прод — `https://<railway-домен>/api`.
- **Заголовки:** `Accept: application/json` на всех запросах, `Content-Type: application/json` на POST/PUT. Без `Accept` ошибки валидации придут редиректом, а не JSON.
- **Авторизация:** нужна только для `PUT /actions/{id}` и `PUT /districts/{id}`. Токен из `POST /login` передаётся как `Authorization: Bearer <token>`. Cookie не используются.
- **CORS:** разрешён один origin из `FRONTEND_URL` бэкенда — должен совпадать с доменом фронта точно (схема, без `/` в конце).

## Важные соглашения

| Что | Как |
|---|---|
| Координаты GeoJSON (`boundary`, `path`) | `[lng, lat]` — как ждёт MapLibre |
| Координаты точек (`center`, `stops`) | объекты `{lat, lng}` |
| Деньги | целые тенге (`100000000` = 100 млн ₸) |
| Метрики в результатах | округлены до 0.1; целые приходят без дробной части (`84`, не `84.0`) |
| `result.*.districts` | объект, **ключи — id района строкой** (`"11"`) |
| Значения «до» | `before` — текущее состояние, `after` — после сценария |
| `transit_coverage` | вычисляется движком (доля жителей в 500 м от остановки), редактировать нельзя |
| `satisfaction` в `after` | вычисляется из улучшений остальных метрик |
| Результаты сценариев | не хранятся — пересчитываются при каждом чтении, поэтому правка модели сразу видна везде |

## Метрики и сферы (демо-справочник)

| key | name | unit | lower_is_better | диапазон |
|---|---|---|---|---|
| `traffic` | Загрузка дорог | % | ✓ | 0–100 |
| `transit_coverage` | Покрытие общественным транспортом | % | ✗ (вычисляется) | 0–100 |
| `travel_time` | Время в пути | индекс | ✓ | 0–200 |
| `co2` | Выбросы CO₂ | индекс | ✓ | 0–200 |
| `heat` | Индекс жары | индекс | ✓ | 0–100 |
| `air` | Качество воздуха | индекс | ✗ | 0–100 |
| `water_loss` | Потери воды в сетях | % | ✓ | 0–100 |
| `social_access` | Доступность соцобъектов | % | ✗ | 0–100 |
| `satisfaction` | Удовлетворённость | индекс | ✗ | 0–100 |

Сферы: `transport`, `climate`, `water`, `social`, `summary`. Источник правды — `GET /city` (`metrics`, `spheres`); таблица выше — для ориентира.

## TypeScript-типы

```ts
// ===== Справочники =====
export type MetricKey =
  | 'traffic' | 'transit_coverage' | 'travel_time' | 'co2' | 'heat'
  | 'air' | 'water_loss' | 'social_access' | 'satisfaction';
export type SphereKey = 'transport' | 'climate' | 'water' | 'social' | 'summary';
export type MetricValues = Partial<Record<MetricKey, number>>;

export interface Sphere { key: SphereKey; name: string }

export interface Metric {
  key: MetricKey;
  name: string;
  unit: string;              // '%' | 'индекс'
  sphere: SphereKey;
  lower_is_better: boolean;
  min: number;
  max: number;
  is_computed: boolean;      // true → не редактируется (transit_coverage)
}

export interface GeoPolygon { type: 'Polygon'; coordinates: [number, number][][] }   // [lng, lat]
export interface GeoLineString { type: 'LineString'; coordinates: [number, number][] } // [lng, lat]
export interface LatLng { lat: number; lng: number }

// ===== GET /city =====
export interface District {
  id: number;
  name: string;              // '12 мкр'
  population: number;
  center: LatLng;
  boundary: GeoPolygon;
  values: MetricValues;      // текущее состояние, включая вычисленный transit_coverage
}
export interface CityResponse {
  spheres: Sphere[];
  metrics: Metric[];
  districts: District[];
  city: MetricValues;        // среднее по городу, взвешенное по населению
}

// ===== GET /actions =====
export interface ActionEffect { metric: MetricKey; delta_pct: number; spill: number } // spill 0..1 — доля эффекта соседям
export interface Action {
  id: number;
  key: string;               // 'smart_lights', 'new_bus_route', …
  name: string;
  sphere: Sphere;
  cost: number;              // ₸
  scope: 'district' | 'route';
  assumption: string;        // текст допущения для блока «Допущения модели»
  source_url: string | null;
  effects: ActionEffect[];   // у new_bus_route пусто: эффект считается через остановки
}

// ===== GET /routes =====
export interface RouteStop { position: number; lat: number; lng: number }
export interface BusRoute {
  id: number;
  key: string;               // 'a' | 'b' | 'c'
  name: string;              // 'Маршрут Б'
  path: GeoLineString;
  stops: RouteStop[];
  district_ids: number[];    // какие районы обслуживает маршрут
}

// ===== Рисование маршрута =====
export interface RoutePointInput { lat: number; lng: number }        // точка в пределах Актау (43.55–43.78, 51.00–51.35)
export interface RoutePreviewRequest { points: RoutePointInput[] }   // 2..25, каждая точка = остановка
export interface RoutePreview {
  path: GeoLineString;      // линия по дорогам (или прямые отрезки, если snapped = false)
  stops: LatLng[];          // точки, притянутые к ближайшей дороге
  snapped: boolean;         // false → OSRM недоступен, линия прямая
  district_ids: number[];   // какие районы обслуживает
}
export interface CreateRouteRequest extends RoutePreviewRequest { name: string } // ≤ 60 символов
// ответ POST /routes — BusRoute (201), key вида 'u-xxxxxxxx'

// ===== GET /model =====
export interface Coupling { source: MetricKey; target: MetricKey; factor: number }
export interface ModelResponse {
  couplings: Coupling[];
  constants: {
    default_budget: number;
    diminishing_factor: number;
    neighbor_radius_m: number;
    stop_access_radius_m: number;
    coverage_grid: number;
  };
}

// ===== Сценарии =====
export type ScenarioItemInput =
  | { action_id: number; district_id: number; route_id?: null; quantity?: 1 | 2 | 3 }  // scope = 'district'
  | { action_id: number; route_id: number; district_id?: null; quantity?: 1 | 2 | 3 }; // scope = 'route'

export interface CreateScenarioRequest {
  name: string;              // ≤ 120 символов
  budget?: number;           // по умолчанию 100 000 000
  district_id?: number | null; // район фокуса (для сравнения и «до/после» района)
  items: ScenarioItemInput[];  // 1..20
}

export interface ScenarioItem {
  id: number;
  action_id: number;
  action_key: string;
  action_name: string;
  district_id: number | null;
  route_id: number | null;
  quantity: number;
}
export interface Scenario {
  id: number;
  name: string;
  source: 'manual' | 'ai';
  budget: number;
  district_id: number | null;
  cost: number;              // сумма cost × quantity
  items: ScenarioItem[];
  created_at: string;        // ISO 8601
}

export interface SimulationSide {
  city: MetricValues;
  districts: Record<string, MetricValues>; // ключ — id района строкой
}
export interface Assumptions {
  actions: { key: string; name: string; assumption: string; source_url: string | null }[];
  couplings: Coupling[];
}
export interface SimulationResult {
  before: SimulationSide;
  after: SimulationSide;
  cost: number;
  budget: number;
  over_budget: boolean;      // true, если модель подорожала после сохранения
  assumptions: Assumptions;
}
// Вклад пункта сценария в район фокуса: насколько иначе было бы без него (прогон без этого пункта).
// Вклады не обязаны складываться в итог — мешают связи метрик и убывающая отдача.
export interface Contribution { label: string; deltas: MetricValues } // label — действие или маршрут, «×2» при повторе
export interface ScenarioWithResult {
  scenario: Scenario;
  result: SimulationResult;
  contributions?: Contribution[]; // только в POST /scenarios и GET /scenarios/{id}; [] без district_id
}

// ===== GET /compare =====
export interface LabeledScenario extends ScenarioWithResult { label: 'A' | 'B' | 'C' }
export interface CompareResponse {
  scenarios: LabeledScenario[];
  explanation: string;       // может содержать \n — рендерить с white-space: pre-line
}

// ===== POST /ai/plan =====
export interface Goal { metric: MetricKey; direction: 'decrease' | 'increase'; weight: number }
export interface AiPlanRequest { prompt: string } // 5..500 символов
export interface AiPlanResponse {
  intent: {
    district_id: number;
    district_name: string;
    district_auto: boolean;   // true — район в запросе не назван, взят самый проблемный по главной цели
    goals: Goal[];
    budget: number;
    fallback: boolean;       // true → OpenAI недоступен, сработал разбор по ключевым словам
  };
  stats: { combinations: number; within_budget: number }; // для строки «перебрано N комбинаций»
  scenarios: LabeledScenario[]; // 0..3, уже сохранены (source = 'ai')
  explanation: string;
}

// ===== Авторизация и редактирование модели =====
export interface LoginRequest { email: string; password: string }
export interface LoginResponse { token: string; name: string }

export interface UpdateActionRequest {
  cost: number;
  effects: ActionEffect[];   // полностью заменяет эффекты; transit_coverage нельзя
}
// ответ PUT /actions/{id} — Action

export interface UpdateDistrictRequest {
  population?: number;
  values?: MetricValues;     // только не вычисляемые метрики, в пределах min..max
}
export interface UpdateDistrictResponse { id: number; population: number; values: MetricValues }

// ===== Ошибки =====
export interface ApiError { message: string }
export interface ValidationError extends ApiError { errors: Record<string, string[]> } // ключи вида 'items.0.route_id'
export interface BudgetExceededError extends ApiError { error: 'budget_exceeded'; over: number }
```

## Эндпоинты

| Метод | Путь | Auth | Лимит | Ответ |
|---|---|---|---|---|
| GET | `/city` | — | — | `CityResponse` |
| GET | `/actions` | — | — | `Action[]` |
| GET | `/routes` | — | — | `BusRoute[]` |
| POST | `/routes/preview` | — | 60/мин | `RoutePreview` |
| POST | `/routes` | — | 20/мин | 201 `BusRoute` |
| GET | `/model` | — | — | `ModelResponse` |
| GET | `/scenarios` | — | — | `Scenario[]` (последние 50, новые первыми) |
| POST | `/scenarios` | — | 60/мин | 201 `ScenarioWithResult` |
| GET | `/scenarios/{id}` | — | — | `ScenarioWithResult` |
| GET | `/compare?ids=1,2,3` | — | 30/мин | `CompareResponse` |
| POST | `/ai/plan` | — | 10/мин | `AiPlanResponse` |
| POST | `/login` | — | 5/мин | `LoginResponse` |
| PUT | `/actions/{id}` | Bearer | — | `Action` |
| PUT | `/districts/{id}` | Bearer | — | `UpdateDistrictResponse` |

### POST /scenarios

```json
{
  "name": "Транспорт + тень",
  "district_id": 11,
  "items": [
    { "action_id": 1, "route_id": 2 },
    { "action_id": 3, "district_id": 11 },
    { "action_id": 5, "district_id": 11 }
  ]
}
```

Ответ 201 (сокращено):

```json
{
  "scenario": {
    "id": 2, "name": "Транспорт + тень", "source": "manual", "budget": 100000000,
    "district_id": 11, "cost": 70000000,
    "items": [
      { "id": 4, "action_id": 1, "action_key": "new_bus_route", "action_name": "Новый автобусный маршрут", "district_id": null, "route_id": 2, "quantity": 1 },
      { "id": 5, "action_id": 3, "action_key": "smart_lights", "action_name": "Умные светофоры", "district_id": 11, "route_id": null, "quantity": 1 }
    ],
    "created_at": "2026-09-23T23:52:09+00:00"
  },
  "result": {
    "before": { "city": { "traffic": 56.1, "transit_coverage": 44.3, "…": 0 }, "districts": { "11": { "traffic": 84, "transit_coverage": 0, "…": 0 } } },
    "after":  { "city": { "…": 0 }, "districts": { "11": { "traffic": 59, "co2": 85.7, "heat": 71.4, "satisfaction": 72.6, "transit_coverage": 72.4, "…": 0 } } },
    "cost": 70000000, "budget": 100000000, "over_budget": false,
    "assumptions": {
      "actions": [{ "key": "smart_lights", "name": "Умные светофоры", "assumption": "Экспертная оценка (демо), заменить источником.", "source_url": null }],
      "couplings": [{ "source": "transit_coverage", "target": "traffic", "factor": -0.35 }, { "source": "traffic", "target": "co2", "factor": 0.5 }]
    }
  }
}
```

Правила `items`: действие со `scope: "district"` требует `district_id` и не принимает `route_id`; со `scope: "route"` — наоборот. Повтор одного действия в одном районе даёт 60% эффекта (убывающая отдача).

### POST /ai/plan

```json
{ "prompt": "Уменьши пробки в 12 мкр и не забудь про жару, бюджет 100 млн" }
```

Ответ 200 (сокращено):

```json
{
  "intent": {
    "district_id": 11, "district_name": "12 мкр",
    "goals": [{ "metric": "traffic", "direction": "decrease", "weight": 1 }, { "metric": "heat", "direction": "decrease", "weight": 0.5 }],
    "budget": 100000000, "fallback": true
  },
  "stats": { "combinations": 47, "within_budget": 43 },
  "scenarios": [{ "label": "A", "scenario": { "id": 5, "name": "A · Маршрут Б + Выделенная полоса + …", "source": "ai", "…": 0 }, "result": { "…": 0 } }],
  "explanation": "A «Маршрут Б + …»: Загрузка дорог −27.4, …; стоимость 100 млн ₸.\nB «…»: …"
}
```

- Район в запросе обязателен («12 мкр», «27 микрорайон»), иначе 422 `{ "message": "Не удалось определить район. Укажите его, например: «12 мкр»." }`.
- Если ничего не укладывается в бюджет — 200 с `scenarios: []` и пояснением в `explanation`.
- Ответ может идти несколько секунд (OpenAI): показывайте состояние загрузки.

### POST /routes/preview и POST /routes

```json
{ "name": "Маршрут · 12 мкр", "points": [{ "lat": 43.6601, "lng": 51.1601 }, { "lat": 43.6699, "lng": 51.1699 }] }
```

- `preview` (без `name`) → `RoutePreview`: линия по дорогам через OSRM; если OSRM недоступен — прямые отрезки и `snapped: false`, ответ всё равно 200.
- `POST /routes` сохраняет маршрут → 201 `BusRoute`; его `id` сразу можно использовать в `items[].route_id` сценария с действием `new_bus_route`.
- Точка вне Актау → 422 `errors["points.N.lat"] = ["Точка вне Актау."]`; меньше двух точек → 422 `errors.points`.

### GET /compare?ids=2,3

До 3 id через запятую. Возвращает `CompareResponse`: метки A/B/C в порядке id. Неверный формат или больше 3 id — 422, несуществующий id — 404.

### POST /login

```json
{ "email": "akimat@citylab.kz", "password": "…" }
```

→ `{ "token": "1|EcjM…", "name": "Акимат" }`. Храните токен в памяти или `sessionStorage`; срок действия не ограничен.

### PUT /actions/{id} (Bearer)

```json
{ "cost": 10000000, "effects": [{ "metric": "traffic", "delta_pct": -6, "spill": 0.3 }, { "metric": "co2", "delta_pct": -2, "spill": 0.3 }] }
```

→ обновлённый `Action`. `effects` целиком заменяет старые; `delta_pct ∈ [−100, 100]`, `spill ∈ [0, 1]`.

### PUT /districts/{id} (Bearer)

```json
{ "population": 18000, "values": { "traffic": 90 } }
```

→ `{ "id": 11, "population": 18000, "values": { "traffic": 90, "…": 0 } }`

## Ошибки

| Код | Когда | Тело |
|---|---|---|
| 401 | нет/неверный токен на PUT | `{ "message": "Unauthenticated." }` |
| 404 | нет сценария/района/действия | `{ "message": "…" }` |
| 422 | валидация | `ValidationError` — показывайте `errors[поле][0]` рядом с полем, иначе `message` |
| 422 | превышен бюджет (POST /scenarios) | `BudgetExceededError`: `{ "message": "Сценарий превышает бюджет на 10 000 000 ₸.", "error": "budget_exceeded", "over": 10000000 }` |
| 429 | превышен лимит запросов | `{ "message": "Too Many Attempts." }` |

Часть стандартных сообщений валидации Laravel пока на английском (`The ids field format is invalid.`), доменные — на русском.

## Сценарий демо → вызовы API

1. **Город:** `GET /city` + `GET /actions` + `GET /routes` один раз при загрузке; `GET /scenarios` для «Мои сценарии».
2. **Район:** данные уже есть в `/city`; действия фильтруются по `scope`, маршруты — по `district_ids.includes(district.id)`.
3. **SIMULATE:** `POST /scenarios` → карточка «до/после» из `result`, анимация по `before`/`after`.
4. **City AI:** `POST /ai/plan` → 3 карточки → «Сравнить» = `GET /compare?ids=<id A>,<id B>,<id C>`.
5. **Модель:** `POST /login` → `GET /actions` + `GET /model` → `PUT /actions/{id}` → перезапрос `GET /compare` покажет пересчёт.
