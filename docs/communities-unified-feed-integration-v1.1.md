# Communities — Unified Feed / Profile / Bookmarks Integration v1.1

> Полностью заменяет `communities-feed-integration-v1.0` (подход с `activity_events`).
> Единый источник ленты — таблица `feed_items` (projection/index), без merge двух запросов.
> Только PostgreSQL. Никаких SQLite-веток и fallback'ов.
> Основано на реальном коде: `FeedPost`, `Feed.php`, `Bookmark`, `ProfileController`, текущая pgsql-схема.
> Статус: **v1.1 — design, ready for implementation**.

---

## A. Findings from current PostgreSQL DB/codebase

Проверено по схеме `docs/schema-dump.sql` и исходникам.

### Feed

| Факт | Значение |
|---|---|
| `feed_posts.id` | `BIGSERIAL` (BIGINT) |
| `feed_posts.user_id` | `BIGINT NULL` — nullable, для whisper-постов `user_id = NULL` |
| `feed_posts.body` | `TEXT` — **plaintext** |
| `feed_posts.visibility` | `VARCHAR(12)` — `'friends' | 'public'` |
| `feed_posts.is_whisper` | `BOOLEAN` — анонимный пост |
| `feed_posts.expires_at`, `deleted_at` | `TIMESTAMP(0)` — TTL + soft delete |
| Индексы | `(visibility, created_at)`, `(user_id, created_at)`, `(expires_at)` |

`FeedPost` model — реальные scope'ы (опираемся на них, не выдумываем новые):
- `scopeVisibleTo(User, ?array $friendIds)` — public OR own OR (friends + author∈friendIds)
- `scopeForTab(User, string $tab, ?array $friendIds)` — `all` / `mine` / `friends`
- `scopeLive()` — `expires_at IS NULL OR expires_at > now()`
- `scopeVisibleOnProfile()` — `is_whisper = false`
- `isVisibleTo(User, ?friendIds): bool`, `isExpired(): bool`
- `FeedPost::booted()`:
  - `saving`: whisper → `visibility = public`, `user_id = NULL`
  - `deleted`: проставляет `Bookmark.original_deleted = true` где `bookmarkable_type = FeedPost::class AND bookmarkable_id = post->id`

`Feed.php` (Livewire) `render()`:
- `FeedPost::query()->with([...])->withCount([...])->live()->forTab($user,$tab,$friendIds)->latest()->simplePaginate(25)`
- `->latest()` = сортировка по `created_at DESC`
- bookmark lookup: `Bookmark::where('user_id',…)->where('bookmarkable_type', FeedPost::class)->whereIn('bookmarkable_id',$postIds)`

`ProfileController::show()`:
- `recentPosts = $user->feedPosts()->visibleTo($viewer,$friendIds)->visibleOnProfile()->live()->latest()->limit(5)->get()`
- `visiblePostsCount = …->visibleTo()->visibleOnProfile()->live()->count()`
- `$canSee(string $setting)` closure: `isSelf → true`, иначе `everyone→true / friends→isFriend / none→false`

### Bookmarks

| Факт | Значение |
|---|---|
| `bookmarks.id` | `BIGSERIAL` |
| `bookmarks.bookmarkable_id` | `BIGINT NOT NULL` — **не вмещает UUID** community_posts |
| `bookmarks.bookmarkable_type` | `VARCHAR(255)` — **реально хранится FQCN** (`App\Models\FeedPost`), не alias `'feed_post'` |
| Уникальность | `UNIQUE(user_id, bookmarkable_type, bookmarkable_id)` |
| Индексы | `(bookmarkable_type, bookmarkable_id)`, `(user_id, created_at)` |
| Snapshot | `snapshot_body TEXT`, `snapshot_author_id`, `snapshot_author_name`, `snapshot_is_whisper`, `snapshot_posted_at`, `source_label`, `original_deleted BOOL` |
| `Bookmark::$fillable` | community-полей нет; `bookmarkable()` = `morphTo()` |

> **Важно:** в коде `bookmarkable_type` = `FeedPost::class`. Комментарий в schema-dump про alias `'feed_post'` — некорректен. Для community нужно либо хранить FQCN `App\Models\CommunityPost`, либо ввести явный `Relation::enforceMorphMap([...])`. В v1.1 — **вводим явный morph map** (см. §N), это безопаснее для UUID-ключей.

### Profile / Privacy

`profile_settings` (BIGSERIAL, `TIMESTAMP(0)`):
`profile_access`, `online_status_visibility`, `shared_friends_count_visibility`, `feed_posts_count_visibility`, `profile_posts_visibility`, `avatar_visibility` — все `VARCHAR(12) NOT NULL DEFAULT 'everyone'`; `show_shared_chats`, `show_shared_groups` (bool), `bio`.

`ProfileSetting`: `AUDIENCE_NONE='none'`, `AUDIENCE_FRIENDS='friends'`, `AUDIENCE_EVERYONE='everyone'`; `audienceValues()` → `['none','friends','everyone']`.

### Friends / Keys / Chats

- `friends` — bidirectional rows; `User::friendIds()`, `User::isFriendWith()`.
- Blocks/mutes — нет. В v1.1 не учитываем.
- Текущие user keys — one-per-user. Для community E2EE нужна **per-device** модель (`user_device_keys`).
- `conversations`/`messages` — отдельный E2EE-чат. **Не смешивать** с communities (workspace + feed/profile integration).

### Timestamps

Вся схема — `TIMESTAMP(0)`. Новые миграции — тоже `timestamp(0)`. **Все application timestamps — UTC.** (Презентационная таймзона — забота слоя представления, не БД.)

---

## B. Weak spots in current integration document (v1.0)

1. **`activity_events` как источник ленты** — порождает второй независимый поток. Не годится: лента должна быть единой.
2. **Два запроса + PHP merge** (`feed_posts` + `activity_events`) — ломает порядок и пагинацию (см. §C).
3. **`bookmarks.bookmarkable_id BIGINT`** — не поддерживает UUID community_posts.
4. **`feed_posts.body` plaintext** — community posts (ciphertext, E2EE) нельзя класть в `feed_posts`.
5. **user keys one-per-user** — недостаточно для per-device E2EE; нет ротации по устройствам.
6. **`visibility_scope` в проекции может устареть** — настройки автора/visibility сообщества меняются. Проекция не может быть source of truth для доступа.
7. **Утечка metadata private community** — если фильтрация только в SQL, ошибка раскрывает имя сообщества/темы не-участнику.
8. **`feed_posts.user_id` nullable** (whispers) — значит `feed_items.actor_id` тоже обязан быть nullable.
9. **SQLite caveats** — больше не нужны: проект только на PostgreSQL.

---

## C. Why feed_items is required

Нельзя так:

```php
$feedPosts = FeedPost::latest()->simplePaginate(25);
$communityEvents = ActivityEvent::latest()->limit(25)->get();
$items = mergeAndSort($feedPosts, $communityEvents);
```

Почему это сломано:
- **Дубли между страницами** — две независимые пагинации сдвигаются друг относительно друга.
- **Пропуски** — элемент, попавший «между» окнами двух запросов, не показывается никогда.
- **Нестабильный порядок** — `LIMIT 25` каждого источника ≠ топ-25 объединённого таймлайна.
- **Нет корректного cursor pagination** — невозможно построить единый стабильный курсор по двум таблицам.
- **Overfetch/фильтрация невидимого** — нельзя добрать «ещё видимых», т.к. границы окна разные.
- **Нет единого timeline** — порядок зависит от того, как PHP смержил, а не от данных.

Правильно: **все элементы общей ленты читаются из `feed_items`** — одна таблица, один `ORDER BY`, один курсор. `feed_items` хранит только metadata + ссылку на источник; контент подгружается батчами из source-таблиц; финальная видимость — через `FeedVisibilityService`.

---

## D. Final unified feed architecture

```
feed_posts          ← source of truth: обычные plaintext-посты (БЕЗ изменений)
community_posts      ← source of truth: E2EE-посты сообществ (ciphertext)
ephemeral_*          ← source of truth: эфемерные пространства (НЕ проецируются)

           │ projector (idempotent upsert)
           ▼
feed_items           ← projection/index. НЕ source of truth.
                       только metadata + ссылки. НЕ plaintext, НЕ ciphertext.
                       единая сортировка + cursor pagination.

           │ read
           ▼
Unified feed  ──► SQL prefilter ──► overfetch ──► FeedVisibilityService ──► render
Profile activity ─────────────────────────────────────────────────────────┘ (surface=profile_activity)
Bookmarks ─────────────────────────────────► FeedVisibilityService (surface=bookmark)
```

Принципы:
- `feed_items` — денормализованный индекс. Можно полностью перестроить из source-таблиц.
- Проекция **не** решает доступ. `show_in_feed` / `visibility_scope` — только грубый SQL-prefilter для производительности. Точное решение — всегда `FeedVisibilityService`.
- Контент рендера всегда грузится из source-таблиц (батчем), не из `feed_items`.

---

## E. PostgreSQL/Laravel migration for feed_items

`database/migrations/xxxx_create_feed_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            // nullable: feed_posts.user_id = NULL для whisper
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();

            $table->string('item_type', 50);
            $table->string('source_type', 50);
            // feed_post.id (bigint-as-string) ИЛИ community UUID
            $table->string('source_id', 64);

            $table->uuid('community_id')->nullable();
            $table->uuid('topic_id')->nullable();
            $table->uuid('post_id')->nullable();

            $table->string('visibility_scope', 30)->default('public');

            $table->boolean('show_in_feed')->default(true);
            $table->boolean('show_in_profile_activity')->default(true);

            $table->timestamp('sort_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->timestamp('deleted_at', 0)->nullable();
        });

        DB::statement("
            ALTER TABLE feed_items
              ADD CONSTRAINT feed_items_item_type_check CHECK (item_type IN (
                'feed_post_created','community_post_created','community_created',
                'community_joined','community_role_changed','community_topic_created'
              )),
              ADD CONSTRAINT feed_items_source_type_check CHECK (source_type IN (
                'feed_post','community_post','community','community_topic','community_member'
              )),
              ADD CONSTRAINT feed_items_visibility_scope_check CHECK (visibility_scope IN (
                'public','friends','community_members_only','private'
              ))
        ");

        // Общая лента: горячий путь
        DB::statement("
            CREATE INDEX feed_items_feed_idx
              ON feed_items (show_in_feed, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ");
        // Активность в профиле
        DB::statement("
            CREATE INDEX feed_items_profile_activity_idx
              ON feed_items (actor_id, show_in_profile_activity, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ");
        // Идемпотентность проекции
        DB::statement("
            CREATE UNIQUE INDEX feed_items_source_unique
              ON feed_items (source_type, source_id, item_type)
        ");
        // Cleanup по сообществу
        DB::statement("
            CREATE INDEX feed_items_community_idx
              ON feed_items (community_id, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ");
        // Cleanup по посту
        DB::statement("
            CREATE INDEX feed_items_post_idx
              ON feed_items (post_id)
              WHERE post_id IS NOT NULL
        ");
        // Фильтр по типу
        DB::statement("
            CREATE INDEX feed_items_type_idx
              ON feed_items (item_type, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
```

Замечания:
- `actor_id` → `nullOnDelete()` (whisper и удалённые авторы).
- `source_id VARCHAR(64)` — общий тип для bigint-as-string и UUID.
- `feed_items_source_unique(source_type, source_id, item_type)` — гарант идемпотентности проектора.
- Partial-индексы (`WHERE deleted_at IS NULL`) — нативный PostgreSQL через `DB::statement`.

---

## F. FeedItem model and source loading

`app/Models/FeedItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'actor_id', 'item_type', 'source_type', 'source_id',
    'community_id', 'topic_id', 'post_id',
    'visibility_scope', 'show_in_feed', 'show_in_profile_activity', 'sort_at',
])]
class FeedItem extends Model
{
    use SoftDeletes;

    public const SOURCE_FEED_POST = 'feed_post';
    public const SOURCE_COMMUNITY_POST = 'community_post';

    protected function casts(): array
    {
        return [
            'show_in_feed' => 'boolean',
            'show_in_profile_activity' => 'boolean',
            'sort_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CommunityTopic::class, 'topic_id');
    }
}
```

**Загрузка источников без N+1** — батчем по типу:

```php
$feedPostIds = $items
    ->where('source_type', FeedItem::SOURCE_FEED_POST)
    ->pluck('source_id')
    ->map(fn ($id) => (int) $id);

$communityPostIds = $items
    ->where('source_type', FeedItem::SOURCE_COMMUNITY_POST)
    ->pluck('source_id');

$feedPosts = FeedPost::query()
    ->whereIn('id', $feedPostIds)
    ->with(['author', 'attachments', 'votes', 'comments', 'poll.options'])
    ->get()
    ->keyBy(fn ($p) => (string) $p->id);

$communityPosts = CommunityPost::query()
    ->whereIn('id', $communityPostIds)
    ->with(['author', 'community', 'topic'])
    ->get()
    ->keyBy('id'); // UUID

// Собрать ViewModel: для community — только metadata, без ciphertext.
$cards = $items->map(function (FeedItem $item) use ($feedPosts, $communityPosts) {
    return match ($item->source_type) {
        FeedItem::SOURCE_FEED_POST =>
            FeedPostCard::from($feedPosts[$item->source_id] ?? null, $item),
        FeedItem::SOURCE_COMMUNITY_POST =>
            CommunityPostPreviewCard::from($communityPosts[$item->source_id] ?? null, $item),
        default => null,
    };
})->filter();
```

`CommunityPostPreviewCard` отдаёт только: автор, community, topic, `created_at`, `locked` флаг, open-URL. Никогда — ciphertext.

---

## G. FeedItemProjector / observers

`app/Services/FeedItemProjector.php`:

```php
final class FeedItemProjector
{
    public function projectFeedPostCreated(FeedPost $post): FeedItem
    {
        return FeedItem::query()->updateOrCreate(
            ['source_type' => 'feed_post', 'source_id' => (string) $post->id, 'item_type' => 'feed_post_created'],
            [
                'actor_id'                 => $post->user_id, // nullable (whisper)
                'community_id'             => null,
                'topic_id'                 => null,
                'post_id'                  => null,
                'visibility_scope'         => $post->visibility, // 'public' | 'friends'
                'show_in_feed'             => true,
                'show_in_profile_activity' => true,
                'sort_at'                  => $post->created_at,
                'deleted_at'               => $post->deleted_at,
            ]
        );
    }

    public function projectCommunityPostCreated(CommunityPost $post): FeedItem
    {
        $community = $post->community;

        $scope = match (true) {
            $community->visibility === 'private' => 'community_members_only',
            default => match ($post->author->profileSetting?->community_posts_feed_visibility ?? 'everyone') {
                'everyone' => 'public',
                'friends'  => 'friends',
                default    => 'private', // none
            },
        };

        return FeedItem::query()->updateOrCreate(
            ['source_type' => 'community_post', 'source_id' => $post->id, 'item_type' => 'community_post_created'],
            [
                'actor_id'                 => $post->author_id,
                'community_id'             => $post->community_id,
                'topic_id'                 => $post->topic_id,
                'post_id'                  => $post->id,
                'visibility_scope'         => $scope,
                'show_in_feed'             => (bool) $community->allow_posts_in_member_feed,
                'show_in_profile_activity' => true,
                'sort_at'                  => $post->created_at,
                'deleted_at'               => $post->deleted_at,
            ]
        );
    }

    public function projectCommunityJoined(CommunityMember $member): ?FeedItem
    {
        if ($member->status !== 'active') {
            return null;
        }

        return FeedItem::query()->updateOrCreate(
            ['source_type' => 'community_member', 'source_id' => $member->id, 'item_type' => 'community_joined'],
            [
                'actor_id'                 => $member->user_id,
                'community_id'             => $member->community_id,
                'topic_id'                 => null,
                'post_id'                  => null,
                'visibility_scope'         => $this->memberActivityScope($member),
                'show_in_feed'             => false,
                'show_in_profile_activity' => true,
                'sort_at'                  => $member->activated_at ?? $member->created_at,
            ]
        );
    }

    public function projectCommunityRoleChanged(CommunityMember $member, string $oldRole, string $newRole): ?FeedItem
    {
        if ($oldRole === $newRole) {
            return null;
        }

        return FeedItem::query()->updateOrCreate(
            ['source_type' => 'community_member', 'source_id' => $member->id, 'item_type' => 'community_role_changed'],
            [
                'actor_id'                 => $member->user_id,
                'community_id'             => $member->community_id,
                'visibility_scope'         => $this->memberActivityScope($member),
                'show_in_feed'             => false,
                'show_in_profile_activity' => true,
                'sort_at'                  => now(),
            ]
        );
    }

    public function deleteForSource(string $sourceType, string $sourceId): void
    {
        FeedItem::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update(['deleted_at' => now()]);
    }

    public function deleteForCommunity(string $communityId): void
    {
        FeedItem::query()->where('community_id', $communityId)->update(['deleted_at' => now()]);
    }

    public function deleteForTopic(string $topicId): void
    {
        FeedItem::query()->where('topic_id', $topicId)->update(['deleted_at' => now()]);
    }
}
```

Гарантии:
- **Идемпотентность** — `updateOrCreate` по `(source_type, source_id, item_type)` (есть UNIQUE-индекс). Повторный retry/observer не плодит дубли.
- Не создавать `feed_item` для уже удалённого/истёкшего источника (или сразу проставлять `deleted_at`).
- `feed_items` полностью перестраивается из source-таблиц (backfill, §H).

**Observers** — `FeedPostObserver`, `CommunityPostObserver`, `CommunityMemberObserver` вызывают соответствующие методы на `created/updated/deleted`. `ephemeral_space_posts` observer'ов не имеют — **никогда не проецируются**.

> `FeedPost::booted()` уже содержит `static::deleted()` (синк `bookmarks.original_deleted`). Добавляем туда же вызов `app(FeedItemProjector::class)->deleteForSource('feed_post', (string)$post->id)` — либо отдельным `FeedPostObserver`. Не дублировать логику.

---

## H. Backfill existing feed_posts

Команда `php artisan feed:backfill-items` (chunked, идемпотентна):

```php
FeedPost::query()
    ->withTrashed()
    ->orderBy('id')
    ->chunkById(500, function ($posts) {
        $rows = $posts->map(fn (FeedPost $p) => [
            'actor_id'                 => $p->user_id,
            'item_type'                => 'feed_post_created',
            'source_type'              => 'feed_post',
            'source_id'                => (string) $p->id,
            'community_id'             => null,
            'topic_id'                 => null,
            'post_id'                  => null,
            'visibility_scope'         => $p->visibility,
            'show_in_feed'             => true,
            'show_in_profile_activity' => true,
            'sort_at'                  => $p->created_at,
            'created_at'               => now(),
            'updated_at'               => now(),
            'deleted_at'               => $p->deleted_at,
        ])->all();

        DB::table('feed_items')->upsert(
            $rows,
            ['source_type', 'source_id', 'item_type'], // конфликт-таргет = UNIQUE
            ['actor_id', 'visibility_scope', 'sort_at', 'deleted_at', 'updated_at']
        );
    });
```

`upsert` по конфликт-таргету `feed_items_source_unique` — повторный запуск безопасен. Аналогичный backfill для `community_posts`, когда таблица существует.

---

## I. Unified feed cursor pagination

Курсор: `(sort_at, id)`. `ORDER BY sort_at DESC, id DESC`. Без `OFFSET`.

```php
// $cursor = ['sort_at' => '2026-05-19 08:00:00', 'id' => 12345] | null
$q = FeedItem::query()
    ->whereNull('deleted_at')
    ->where('show_in_feed', true)
    ->orderByDesc('sort_at')
    ->orderByDesc('id');

if ($cursor) {
    $q->where(function ($w) use ($cursor) {
        $w->where('sort_at', '<', $cursor['sort_at'])
          ->orWhere(function ($w2) use ($cursor) {
              $w2->where('sort_at', '=', $cursor['sort_at'])
                 ->where('id', '<', $cursor['id']);
          });
    });
}
```

Стабильность: tie-break по `id` гарантирует отсутствие дублей/пропусков между страницами даже при равных `sort_at`. Курсор для следующей страницы строится по **последнему просканированному raw-элементу**, не по последнему видимому (см. §J).

---

## J. SQL prefilter + policy overfetch

### 1. Контекст viewer'а (один раз на запрос)

```php
$friendIds = $viewer->friendIds(); // int[]
$activeCommunityIds = CommunityMember::query()
    ->where('user_id', $viewer->id)
    ->where('status', 'active')
    ->pluck('community_id'); // uuid[]
```

### 2. SQL prefilter (грубый, для производительности — НЕ финальный)

```php
$base = FeedItem::query()
    ->whereNull('deleted_at')
    ->where('show_in_feed', true)
    ->where(function ($w) use ($viewer, $friendIds, $activeCommunityIds) {
        $w->where('visibility_scope', 'public')
          ->orWhere('actor_id', $viewer->id)
          ->orWhere(function ($w2) use ($friendIds) {
              $w2->where('visibility_scope', 'friends')
                 ->whereIn('actor_id', $friendIds);
          })
          ->orWhere(function ($w3) use ($activeCommunityIds) {
              $w3->where('visibility_scope', 'community_members_only')
                 ->whereIn('community_id', $activeCommunityIds);
          });
    })
    ->orderByDesc('sort_at')
    ->orderByDesc('id');
```

> `visibility_scope` в проекции может быть устаревшим. Поэтому prefilter **только сужает** выборку, а финальное решение — `FeedVisibilityService` (§K). Никогда не отдавать элемент в ленту, минуя сервис.

### 3. Overfetch + policy фильтрация

```php
$pageSize = 25;
$rawBatch = 100;
$maxScan  = 500;

$visible = [];
$scanned = 0;
$cursor  = $incomingCursor;
$lastScannedItem = null;

while (count($visible) < $pageSize && $scanned < $maxScan) {
    $batch = (clone $base)->applyCursor($cursor)->limit($rawBatch)->get();
    if ($batch->isEmpty()) { break; }

    // батч-loading источников (см. §F) — без N+1
    foreach ($batch as $item) {
        $scanned++;
        $lastScannedItem = $item;
        if ($visibilityService->canViewerSeeFeedItem($viewer, $item, 'feed')) {
            $visible[] = $item;
            if (count($visible) === $pageSize) { break; }
        }
    }
    $cursor = ['sort_at' => $lastScannedItem->sort_at, 'id' => $lastScannedItem->id];
}

$nextCursor = $lastScannedItem
    ? ['sort_at' => $lastScannedItem->sort_at, 'id' => $lastScannedItem->id]
    : null;
```

Ключевое: **next cursor строится по последнему просканированному**, не по последнему видимому — иначе невидимые элементы создадут вечный цикл/пропуски. `maxScan = 500` — защита от деградации, когда видимых почти нет.

`Feed.php` (Livewire) переписывается с `simplePaginate` на этот cursor-механизм; UI «Загрузить ещё» передаёт `nextCursor`.

---

## K. FeedVisibilityService

`app/Services/FeedVisibilityService.php`. `$surface ∈ {'feed','profile_activity','bookmark'}`.

```php
public function canViewerSeeFeedItem(User $viewer, FeedItem $item, string $surface = 'feed'): bool
{
    if ($item->deleted_at !== null) {
        return false;
    }

    return match ($item->source_type) {
        'feed_post'      => $this->canSeeFeedPost($viewer, $item, $surface),
        'community_post' => $this->canSeeCommunityPost($viewer, $item, $surface),
        'community', 'community_topic', 'community_member'
                         => $this->canSeeCommunityActivity($viewer, $item, $surface),
        default          => false,
    };
}
```

### canSeeFeedPost — переиспользует существующую логику

```php
private function canSeeFeedPost(User $viewer, FeedItem $item, string $surface): bool
{
    $post = $this->resolveFeedPost($item); // батч-кэш из §F
    if (!$post || $post->trashed() || $post->isExpired()) {
        return false;
    }
    if ($surface === 'profile_activity' && $post->is_whisper) {
        return false; // эквивалент scopeVisibleOnProfile()
    }
    // та же модель доступа, что и FeedPost::isVisibleTo()/scopeVisibleTo()
    return $post->isVisibleTo($viewer); // public OR own (actor_id NULL → whisper public) OR friends∋author
}
```

Whisper: `user_id = NULL`, `visibility = public` → `isVisibleTo()` вернёт true по public-ветке; `actor_id = NULL` не ломает проверку.

### canSeeCommunityPost

```php
private function canSeeCommunityPost(User $viewer, FeedItem $item, string $surface): bool
{
    $post = $this->resolveCommunityPost($item);
    if (!$post || $post->trashed()) { return false; }                       // 2,3
    if ($post->expires_at && $post->expires_at->isPast()) { return false; } // 4
    if ($post->moderation_status !== 'visible') { return false; }           // 5

    $community = $post->community;
    if (!$community || $community->trashed()) { return false; }             // 6
    $topic = $post->topic;
    if (!$topic || $topic->trashed()) { return false; }                     // 7

    $isAuthor = $viewer->id === $post->author_id;
    if ($isAuthor) { return true; }                                         // 8 (источник жив)

    if ($surface === 'feed' && !$community->allow_posts_in_member_feed) {
        return false;                                                       // 9
    }

    if ($community->visibility === 'private') {                             // 10
        return $this->isActiveMember($viewer->id, $community->id);
        // НЕ раскрываем имя community/topic не-участнику (см. §L)
    }

    // public community → настройка автора, зависит от surface       // 11,12,13
    $setting = $post->author->profileSetting;
    $audience = $surface === 'profile_activity'
        ? ($setting?->community_posts_profile_visibility ?? 'everyone')
        : ($setting?->community_posts_feed_visibility ?? 'everyone');

    return match ($audience) {
        'everyone' => true,
        'friends'  => $viewer->isFriendWith($post->author_id),
        default    => false, // none → только автор
    };
}
```

### canSeeCommunityActivity

```php
private function canSeeCommunityActivity(User $viewer, FeedItem $item, string $surface): bool
{
    $actor = $item->actor;
    if (!$actor) { return false; }
    $isSelf = $viewer->id === $actor->id;
    if ($isSelf) { /* профиль самому себе — допускаем */ }

    $ps = $actor->profileSetting;

    if (!$isSelf) {
        if (!$this->audienceAllows($ps?->profile_access ?? 'everyone', $viewer, $actor)) {
            return false;
        }
        if (!$this->audienceAllows($ps?->community_activity_visibility ?? 'friends', $viewer, $actor)) {
            return false;
        }
        $extra = match ($item->item_type) {
            'community_joined'       => $ps?->joined_communities_activity_visibility,
            'community_role_changed' => $ps?->community_roles_visibility,
            default                  => null,
        };
        if ($extra !== null && !$this->audienceAllows($extra, $viewer, $actor)) {
            return false;
        }
    }

    if ($item->community_id) {
        $c = Community::find($item->community_id);
        if ($c && $c->visibility === 'private'
            && !$isSelf && !$this->isActiveMember($viewer->id, $c->id)) {
            return false; // никакой metadata private community
        }
    }
    return true;
}

private function audienceAllows(string $a, User $viewer, User $owner): bool
{
    return match ($a) {
        'everyone' => true,
        'friends'  => $viewer->isFriendWith($owner->id),
        default    => false,
    };
}

private function isActiveMember(int $userId, string $communityId): bool
{
    return CommunityMember::query()
        ->where('community_id', $communityId)
        ->where('user_id', $userId)
        ->where('status', 'active')
        ->exists();
}
```

---

## L. Community post visibility rules

Сводно:

| Условие | Результат |
|---|---|
| `feed_item.deleted_at` ≠ null | скрыт везде |
| post deleted / expired / `moderation_status ≠ visible` | скрыт везде |
| community/topic deleted | скрыт везде |
| viewer = автор, источник жив | виден всегда |
| community private, viewer **не** active member | скрыт; **metadata не отдаётся** |
| community private, viewer active member | виден (ciphertext отдаётся по §O) |
| community public, `allow_posts_in_member_feed = false`, surface=feed | скрыт в общей ленте (но виден в community/topic) |
| community public, author setting (`feed`/`profile`) = `everyone` | виден всем |
| = `friends` | виден только друзьям автора |
| = `none` | виден только автору |

**No metadata leakage:** для private community не-участнику API/ViewModel **не возвращает** `community.name`, `topic.name`, иконку, tint, автора-в-контексте-сообщества. Элемент просто отсутствует в ответе (не «скрытая карточка»).

---

## M. Profile integration through feed_items

Активность профиля читается из `feed_items` (тот же источник, что лента):

```php
$activityQuery = FeedItem::query()
    ->where('actor_id', $profileUser->id)
    ->where('show_in_profile_activity', true)
    ->whereNull('deleted_at')
    ->orderByDesc('sort_at')
    ->orderByDesc('id');

// SQL prefilter: убрать private communities, где viewer не member
if (!$isSelf) {
    $activityQuery->where(function ($w) use ($activeCommunityIds) {
        $w->whereNull('community_id')
          ->orWhereIn('community_id', $activeCommunityIds);
    });
}

$raw = $activityQuery->limit(50)->get();
$activity = $raw
    ->filter(fn (FeedItem $i) =>
        $visibilityService->canViewerSeeFeedItem($viewer, $i, 'profile_activity'))
    ->take(20)
    ->values();
```

`recentPosts` (текущий `feed_posts`-блок в `ProfileController`) можно оставить как есть **или** заменить на `feed_items`-выборку с `source_type IN ('feed_post','community_post')`. Рекомендуется второе — единый механизм.

### Новые поля `profile_settings`

`database/migrations/xxxx_add_community_visibility_to_profile_settings.php`:

```php
Schema::table('profile_settings', function (Blueprint $table) {
    $table->string('profile_communities_visibility', 12)->default('friends');
    $table->string('community_activity_visibility', 12)->default('friends');
    $table->string('community_posts_profile_visibility', 12)->default('friends');
    $table->string('community_posts_feed_visibility', 12)->default('friends');
    $table->string('joined_communities_activity_visibility', 12)->default('friends');
    $table->string('community_roles_visibility', 12)->default('friends');
});
```

> Существующие audience-поля дефолтятся `'everyone'`. Новые community-поля сознательно `'friends'` — более консервативный дефолт для групповых данных. Значения те же: `none|friends|everyone`. Добавить все 6 в `ProfileSetting::$fillable`; `audienceValues()` менять не нужно.

Правила применения:
- `profile_access` — проверяется **первой** (вход в профиль).
- `profile_posts_visibility` — для `feed_post_created`.
- `community_posts_profile_visibility` — для `community_post_created` (surface=profile_activity).
- `community_activity_visibility` — общий гейт для `community_joined / community_role_changed / community_topic_created`.
- `joined_communities_activity_visibility` — дополнительно для `community_joined`.
- `community_roles_visibility` — дополнительно для `community_role_changed`.
- `profile_communities_visibility` — список сообществ в профиле (отдельный блок).
- Private community metadata не показывать не-участнику **даже в профиле автора**.

`SettingsController::updateProfileVisibility()` — добавить 6 правил `['required', Rule::in(ProfileSetting::audienceValues())]`.

---

## N. Bookmarks integration with bookmarkable_key

Проблема: `bookmarkable_id BIGINT` не вмещает UUID. Решение — **аддитивное**, без in-place смены типа (безопаснее, без `ACCESS EXCLUSIVE` на горячую таблицу, без переписывания legacy-строк).

`database/migrations/xxxx_add_bookmarkable_key_to_bookmarks.php`:

```php
public function up(): void
{
    Schema::table('bookmarks', function (Blueprint $table) {
        $table->string('bookmarkable_key', 64)->nullable();
        $table->uuid('community_id')->nullable();
        $table->boolean('access_revoked')->default(false);
    });

    DB::statement("UPDATE bookmarks SET bookmarkable_key = bookmarkable_id::text WHERE bookmarkable_key IS NULL");

    DB::statement("
        CREATE UNIQUE INDEX bookmarks_user_type_key_unique
          ON bookmarks (user_id, bookmarkable_type, bookmarkable_key)
    ");
    DB::statement("
        CREATE INDEX bookmarks_type_key_idx
          ON bookmarks (bookmarkable_type, bookmarkable_key)
    ");
}
```

Последующая запись:
- feed_post bookmark: `bookmarkable_key = (string) feed_post.id`
- community_post bookmark: `bookmarkable_key = community_post.uuid`
- старый `bookmarkable_id` остаётся NOT NULL для legacy feed_post (можно писать туда же `(int)` для feed_post; для community_post — `bookmarkable_id` оставить, например, `0` нельзя из-за FK-семантики? FK на bookmarks.bookmarkable_id отсутствует — это polymorphic, ограничения нет). Для community_post пишем `bookmarkable_id = 0` **только** если NOT NULL обязателен; иначе сделать `bookmarkable_id` nullable отдельной миграцией. **Рекомендация:** отдельной миграцией `bookmarkable_id` → nullable, и для community-закладок не заполнять его. Полное удаление `bookmarkable_id` — следующий major.

### Явный morph map

В `AppServiceProvider::boot()`:

```php
Relation::enforceMorphMap([
    'feed_post'      => \App\Models\FeedPost::class,
    'community_post' => \App\Models\CommunityPost::class,
]);
```

> Сейчас код пишет FQCN в `bookmarkable_type` (`FeedPost::class`). Включение morph map поменяет хранимое значение на alias `'feed_post'`. Нужна data-миграция:
> `UPDATE bookmarks SET bookmarkable_type='feed_post' WHERE bookmarkable_type='App\\Models\\FeedPost'`
> и синхронно поправить `Feed.php` (`->where('bookmarkable_type','feed_post')`) и `FeedPost::booted() deleted` хук. Делать одним PR, иначе закладки «потеряются».

### Community-закладка

- `bookmarkable_type = 'community_post'`, `bookmarkable_key = UUID`
- `snapshot_body = NULL` (E2EE)
- `community_id` заполнить
- `source_label = 'из сообщества'`
- НЕ копировать encrypted attachments в `bookmark_attachments`
- доступ — `FeedVisibilityService::canViewerSeeFeedItem(..., surface:'bookmark')` либо специализированный `canAccessBookmark()`, использующий те же правила §L

Выход из private community:
- закладка **остаётся**;
- **render-time check обязателен** — `access_revoked` может обновляться async, но рендер всегда перепроверяет членство;
- состояния: `Available` / `AccessRevoked` («вы покинули сообщество») / `Unavailable` (`original_deleted` или post удалён/истёк).

`BookmarkController`: валидацию `bookmarkable_id` сменить на `bookmarkable_key` `['required','string','max:64']`; ветка `community_post` — проверка доступа + snapshot без body + `community_id`.

---

## O. E2EE preview/detail behavior

**Preview (feed / profile / bookmark список):**
- НЕ возвращать plaintext.
- НЕ возвращать ciphertext.
- Только metadata: `author`, `community`, `topic`, `created_at`, `locked` флаг, open-URL.

**Detail (внутри community/topic):**
- active member получает `ciphertext + nonce + key_epoch_id`;
- клиент расшифровывает локально;
- если на устройстве нет ключа для `key_epoch_id` → UI «locked / key missing», не plaintext;
- backend **никогда** не расшифровывает и не хранит plaintext/ciphertext в `feed_items`.

---

## P. Notifications

События: `community_invite_received`, `join_request_received`, `join_request_approved`, `key_delivery_pending`, `member_activated`, `community_post_created`, `community_role_changed`, (позже) `community_mention/reply`.

Правила:
- `notification.data` **не содержит** plaintext контента community post — только metadata + ссылка.
- Private community: не-участникам уведомления **не отправляются** вовсе.
- E2EE-посты: в payload только metadata; если на устройстве нет ключа — уведомление в locked-состоянии.
- Использовать существующий канал `via: ['database','broadcast']` (как в проекте), без отдельного `broadcast()` рядом с `notify()`.

---

## Q. Expiration/deletion sync

`feed_items` синхронизируется с источниками (никогда не остаётся «висячим»):

| Триггер | Действие |
|---|---|
| `FeedPost` deleted (soft) | `FeedPost::booted()/Observer` → `projector->deleteForSource('feed_post', id)` (рядом с уже существующим синком `bookmarks.original_deleted`) |
| `FeedPost` expired (`expires_at`) | `feed_item` остаётся, но `canSeeFeedPost()` вернёт false (через `isExpired()`); периодический job может проставить `deleted_at` для cleanup |
| `CommunityPost` deleted | `CommunityPostObserver` → `deleteForSource('community_post', uuid)` |
| `CommunityPost` expired | как у feed_post — фильтр на уровне сервиса; cleanup-job |
| Community deleted (soft) | `deleteForCommunity(uuid)` |
| Topic deleted | `deleteForTopic(uuid)` |
| Member leave/ban в private community | bookmarks: render-time перепроверка; опц. async `access_revoked = true` |

Cleanup job (`feed:prune-items`, ежечасно):

```sql
UPDATE feed_items fi SET deleted_at = now()
WHERE fi.deleted_at IS NULL AND fi.source_type = 'feed_post'
  AND EXISTS (SELECT 1 FROM feed_posts fp
              WHERE fp.id = fi.source_id::bigint
                AND (fp.deleted_at IS NOT NULL
                  OR (fp.expires_at IS NOT NULL AND fp.expires_at < now())));
-- аналогично для community_posts по uuid
```

Идемпотентно (`WHERE deleted_at IS NULL`), повторный запуск безопасен.

---

## R. Migration plan

```
 1. add_community_visibility_to_profile_settings   (6 полей, default 'friends')
 2. create_feed_items_table                        (+ CHECK + partial indexes)
 3. add_bookmarkable_key_to_bookmarks              (+ community_id, access_revoked, backfill key)
 4. (опц.) make_bookmarks_bookmarkable_id_nullable
 5. create_user_device_keys_table                  (per-device E2EE)
 6. communities/core tables                        (если ещё не применены, per db-v1)
 7. add_allow_posts_in_member_feed_to_communities
 8. App\Models\FeedItem                            (+ enforceMorphMap в AppServiceProvider)
 9. App\Services\FeedItemProjector + Observers
10. data-migration bookmarkable_type FQCN → alias  (+ правка Feed.php, FeedPost::booted)
11. backfill: feed_posts → feed_items              (artisan feed:backfill-items)
12. Feed.php: simplePaginate → cursor (читать feed_items, §I/§J)
13. App\Services\FeedVisibilityService
14. ProfileController: recent activity из feed_items
15. BookmarkController: поддержка community_post (bookmarkable_key)
16. cleanup jobs: feed:prune-items + expiration sync
17. tests (§S)
18. feature flag rollout (community_post проекция за флагом; feed_post backfill всегда)
```

Порядок безопасен: шаги 1–4, 7, 11 — аддитивны и backward-compatible. Шаг 10 — единственный, требующий координации (один PR: data + Feed.php + hook).

---

## S. Test plan

```
 1. Существующий feed_post получает feed_item через backfill.
 2. Новый feed_post автоматически создаёт feed_item.
 3. Новый community_post автоматически создаёт feed_item.
 4. Unified feed возвращает feed_post и community_post в правильном порядке sort_at.
 5. Cursor pagination: нет дублей между страницами.
 6. Cursor pagination: нет пропущенных видимых элементов при обычной политике.
 7. public community_post в ленте, если author setting = everyone.
 8. public community_post скрыт, если author setting = none.
 9. public community_post виден только друзьям, если setting = friends.
10. private community_post в ленте только active member'ам.
11. private community_post не раскрывает metadata не-участнику.
12. Автор видит свой community_post даже при community_posts_feed_visibility = none.
13. allow_posts_in_member_feed = false → пост не в общей ленте (но в community виден).
14. community_post в профиле автора, если community_posts_profile_visibility разрешает.
15. community_post скрыт из профиля, если профильные настройки запрещают.
16. private community_post скрыт в профиле для не-участника.
17. Bookmark community_post работает с bookmarkable_key (UUID).
18. Bookmark community_post → locked/unavailable после выхода из private community.
19. feed_item для whisper: actor_id = NULL не ломает видимость (public-ветка).
20. Истёкший feed_post скрывает соответствующий feed_item.
21. Истёкший community_post скрывает соответствующий feed_item.
22. Удаление community soft-удаляет связанные feed_items.
23. Overfetch корректно пропускает невидимые элементы (next cursor по raw, не visible).
24. community_joined в profile activity, но НЕ в общей ленте (show_in_feed = false).
25. role_changed уважает community_roles_visibility.
26. community_joined уважает joined_communities_activity_visibility.
27. ephemeral_space_posts никогда не создают feed_items.
28. В preview ленты/профиля нет ciphertext для community posts.
29. Уведомление о community post не содержит plaintext.
30. Устройство без ключа показывает locked, не plaintext.
31. Проектор идемпотентен: повторный observer/retry не плодит дубли (UNIQUE).
32. backfill повторно — без дублей (upsert по конфликт-таргету).
33. data-migration morph map: старые FQCN-закладки не «теряются».
```

Все тесты — PHPUnit feature-тесты (`php artisan make:test --phpunit`), фабрики для моделей.

---

## T. Risks and mitigations

| Риск | Митигация |
|---|---|
| `visibility_scope` в проекции устаревает (автор сменил настройку) | prefilter только сужает; финал — `FeedVisibilityService` на актуальных настройках. Опц. ре-проекция при смене настроек. |
| Утечка metadata private community | Единственная точка решения — сервис; private+не-участник → элемент **отсутствует** в ответе, не «скрытая карточка». Тесты 11/16. |
| Overfetch деградирует (мало видимого) | `maxScan = 500` cap; next cursor по последнему raw; партиал-индекс `feed_items_feed_idx`. |
| morph map переключение «теряет» закладки | Один PR: data-migration + `Feed.php` + `FeedPost::booted` хук синхронно. Тест 33. |
| Дубли feed_items при retry/двойном observer | `UNIQUE(source_type, source_id, item_type)` + `updateOrCreate`/`upsert`. Тест 31/32. |
| `feed_items` рассинхрон с источником | Проектор идемпотентен + полный rebuild из source + cleanup-job. `feed_items` не source of truth. |
| Whisper (`actor_id NULL`) ломает фильтры | `actor_id` nullable; whisper всегда `visibility_scope='public'`; явный тест 19. |
| Большая `feed_items` со временем | Партиал-индексы; cleanup истёкших/удалённых; партиционирование `feed_items` по `sort_at` — v2. |
| `bookmarkable_id BIGINT NOT NULL` мешает community-закладке | Аддитивный `bookmarkable_key`; `bookmarkable_id` → nullable отдельной миграцией; полное удаление — следующий major. |
| ephemeral активность утечёт в ленту | У ephemeral нет observer'ов проекции; явный тест 27. |
| Community-таблицы ещё не внедрены | Шаги 1–4 аддитивны и работают без communities; проекция community_post — за feature flag. |

---

> Конец v1.1. Документ заменяет v1.0 (`activity_events`). Source of truth: `feed_posts` (plaintext) и `community_posts` (ciphertext); `feed_items` — единый projection-индекс для ленты/профиля; доступ — всегда через `FeedVisibilityService`.
