-- ============================================================
-- skr — PostgreSQL schema dump
-- Generated: 2026-05-19  Engine: pgsql  Schema: public
-- ============================================================

-- ============================================================
-- USERS & AUTH
-- ============================================================

CREATE TABLE users (
    id                      BIGSERIAL       PRIMARY KEY,
    login                   VARCHAR(255)    NOT NULL,
    name                    VARCHAR(255),
    email                   VARCHAR(255),
    email_verified_at       TIMESTAMP(0),
    password                VARCHAR(255)    NOT NULL,
    remember_token          VARCHAR(100),
    pseudonym               VARCHAR(50),
    avatar                  VARCHAR(255),
    backup_code_hash        VARCHAR(255),
    pending_email           VARCHAR(255),
    pending_password_hash   VARCHAR(255),
    two_factor_enabled      BOOLEAN         NOT NULL DEFAULT false,
    two_factor_code         VARCHAR(6),
    two_factor_code_expires_at TIMESTAMP(0),
    last_seen_at            TIMESTAMP(0),
    created_at              TIMESTAMP(0),
    updated_at              TIMESTAMP(0)
);

CREATE UNIQUE INDEX users_login_unique            ON users(login);
CREATE UNIQUE INDEX users_email_unique            ON users(email);
CREATE UNIQUE INDEX users_feed_pseudonym_unique   ON users(pseudonym);

-- ----

CREATE TABLE user_keys (
    id                  BIGSERIAL       PRIMARY KEY,
    user_id             BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    public_key_jwk      TEXT            NOT NULL,
    storage_preference  VARCHAR(10)     NOT NULL DEFAULT 'server',
    key_backup          TEXT,
    key_change_source   VARCHAR(10),
    key_changed_at      TIMESTAMP(0),
    notify_sound        BOOLEAN         NOT NULL DEFAULT true,
    notify_email        BOOLEAN         NOT NULL DEFAULT false,
    notify_email_text   BOOLEAN         NOT NULL DEFAULT false,
    notify_push         BOOLEAN         NOT NULL DEFAULT false,
    notify_push_text    BOOLEAN         NOT NULL DEFAULT false,
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0)
);

CREATE UNIQUE INDEX user_keys_user_id_unique ON user_keys(user_id);

-- ----

CREATE TABLE trusted_devices (
    id          BIGSERIAL   PRIMARY KEY,
    user_id     BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  VARCHAR(64) NOT NULL,
    expires_at  TIMESTAMP(0) NOT NULL,
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

-- ----

CREATE TABLE login_history (
    id          BIGSERIAL       PRIMARY KEY,
    user_id     BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    event       VARCHAR(255),
    created_at  TIMESTAMP(0)
);

-- ----

CREATE TABLE push_subscriptions (
    id          BIGSERIAL   PRIMARY KEY,
    user_id     BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    endpoint    TEXT        NOT NULL,
    p256dh      TEXT        NOT NULL,
    auth        TEXT        NOT NULL,
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

CREATE UNIQUE INDEX push_subscriptions_endpoint_unique ON push_subscriptions(endpoint);

-- ----

CREATE TABLE password_reset_tokens (
    email       VARCHAR(255) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP(0)
);

-- ----

CREATE TABLE personal_access_tokens (
    id              BIGSERIAL       PRIMARY KEY,
    tokenable_type  VARCHAR(255)    NOT NULL,
    tokenable_id    BIGINT          NOT NULL,
    name            TEXT            NOT NULL,
    token           VARCHAR(64)     NOT NULL,
    abilities       TEXT,
    last_used_at    TIMESTAMP(0),
    expires_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX personal_access_tokens_token_unique ON personal_access_tokens(token);
CREATE INDEX personal_access_tokens_tokenable_index ON personal_access_tokens(tokenable_type, tokenable_id);

-- ============================================================
-- PROFILE & PRIVACY
-- ============================================================

CREATE TABLE profile_settings (
    id                              BIGSERIAL       PRIMARY KEY,
    user_id                         BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    bio                             VARCHAR(255),
    show_shared_chats               BOOLEAN         NOT NULL DEFAULT true,
    show_shared_groups              BOOLEAN         NOT NULL DEFAULT true,
    -- audience: 'none' | 'friends' | 'everyone'
    profile_access                  VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    online_status_visibility        VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    shared_friends_count_visibility VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    feed_posts_count_visibility     VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    profile_posts_visibility        VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    avatar_visibility               VARCHAR(12)     NOT NULL DEFAULT 'everyone',
    created_at                      TIMESTAMP(0),
    updated_at                      TIMESTAMP(0)
);

CREATE UNIQUE INDEX profile_settings_user_id_unique ON profile_settings(user_id);

-- ============================================================
-- FRIENDS
-- ============================================================

CREATE TABLE friends (
    id          BIGSERIAL   PRIMARY KEY,
    user_id     BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    friend_id   BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

CREATE UNIQUE INDEX friends_user_id_friend_id_unique ON friends(user_id, friend_id);

-- ----

CREATE TABLE friend_codes (
    id          BIGSERIAL       PRIMARY KEY,
    user_id     BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code        VARCHAR(10)     NOT NULL,
    is_used     BOOLEAN         NOT NULL DEFAULT false,
    is_blocked  BOOLEAN         NOT NULL DEFAULT false,
    expires_at  TIMESTAMP(0)    NOT NULL,
    used_at     TIMESTAMP(0),
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

CREATE UNIQUE INDEX friend_codes_code_unique         ON friend_codes(code);
CREATE INDEX friend_codes_code_is_used_is_blocked    ON friend_codes(code, is_used, is_blocked);
CREATE INDEX friend_codes_user_id_is_blocked         ON friend_codes(user_id, is_blocked);

-- ----

CREATE TABLE friend_requests (
    id              BIGSERIAL       PRIMARY KEY,
    sender_id       BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id     BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    friend_code_id  BIGINT          REFERENCES friend_codes(id) ON DELETE SET NULL,
    status          VARCHAR(255)    NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending', 'accepted', 'declined')),
    is_read         BOOLEAN         NOT NULL DEFAULT false,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

-- Partial unique — only one pending request per sender/receiver pair
CREATE UNIQUE INDEX unique_pending_request ON friend_requests(sender_id, receiver_id);

-- ============================================================
-- FEED
-- ============================================================

CREATE TABLE feed_posts (
    id              BIGSERIAL       PRIMARY KEY,
    user_id         BIGINT          REFERENCES users(id) ON DELETE CASCADE,   -- nullable for whispers
    body            TEXT,
    visibility      VARCHAR(12)     NOT NULL DEFAULT 'friends',               -- 'friends' | 'public'
    is_whisper      BOOLEAN         NOT NULL DEFAULT false,
    -- deprecated attachment columns (superseded by feed_post_attachments)
    attachment_path VARCHAR(255),
    attachment_name VARCHAR(255),
    attachment_mime VARCHAR(255),
    attachment_size BIGINT,
    expires_at      TIMESTAMP(0),
    deleted_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE INDEX feed_posts_visibility_created_at   ON feed_posts(visibility, created_at);
CREATE INDEX feed_posts_user_id_created_at      ON feed_posts(user_id, created_at);
CREATE INDEX feed_posts_expires_at              ON feed_posts(expires_at);

-- ----

CREATE TABLE feed_post_attachments (
    id              BIGSERIAL   PRIMARY KEY,
    feed_post_id    BIGINT      NOT NULL REFERENCES feed_posts(id) ON DELETE CASCADE,
    path            VARCHAR(255) NOT NULL,
    thumbnail_path  VARCHAR(255),
    name            VARCHAR(255),
    mime            VARCHAR(255),
    size            BIGINT,
    position        SMALLINT    NOT NULL,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX feed_post_attachments_feed_post_id_position_unique
    ON feed_post_attachments(feed_post_id, position);

-- ----

CREATE TABLE feed_votes (
    id              BIGSERIAL   PRIMARY KEY,
    feed_post_id    BIGINT      NOT NULL REFERENCES feed_posts(id) ON DELETE CASCADE,
    user_id         BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    value           VARCHAR(4)  NOT NULL,   -- 'up' | 'down'
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX feed_votes_feed_post_id_user_id_unique ON feed_votes(feed_post_id, user_id);
CREATE INDEX        feed_votes_feed_post_id_value          ON feed_votes(feed_post_id, value);

-- ----

CREATE TABLE feed_comments (
    id              BIGSERIAL   PRIMARY KEY,
    feed_post_id    BIGINT      NOT NULL REFERENCES feed_posts(id) ON DELETE CASCADE,
    user_id         BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id       BIGINT      REFERENCES feed_comments(id) ON DELETE CASCADE,
    body            TEXT        NOT NULL,
    deleted_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE INDEX feed_comments_feed_post_id_created_at              ON feed_comments(feed_post_id, created_at);
CREATE INDEX feed_comments_feed_post_id_parent_id_created_at    ON feed_comments(feed_post_id, parent_id, created_at);
CREATE INDEX feed_comments_deleted_at                           ON feed_comments(deleted_at);

-- ----

CREATE TABLE feed_comment_votes (
    id                  BIGSERIAL   PRIMARY KEY,
    feed_comment_id     BIGINT      NOT NULL REFERENCES feed_comments(id) ON DELETE CASCADE,
    user_id             BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    value               VARCHAR(4)  NOT NULL,   -- 'up' | 'down'
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0)
);

CREATE UNIQUE INDEX feed_comment_votes_feed_comment_id_user_id_unique
    ON feed_comment_votes(feed_comment_id, user_id);
CREATE INDEX feed_comment_votes_feed_comment_id_value
    ON feed_comment_votes(feed_comment_id, value);

-- ----

CREATE TABLE feed_comment_edits (
    id                  BIGSERIAL   PRIMARY KEY,
    feed_comment_id     BIGINT      NOT NULL REFERENCES feed_comments(id) ON DELETE CASCADE,
    body                TEXT        NOT NULL,   -- previous body before the edit
    created_at          TIMESTAMP(0) NOT NULL
    -- no updated_at: immutable history
);

CREATE INDEX feed_comment_edits_feed_comment_id_created_at
    ON feed_comment_edits(feed_comment_id, created_at);

-- ============================================================
-- POLLS  (attached to feed_posts)
-- ============================================================

CREATE TABLE polls (
    id              BIGSERIAL       PRIMARY KEY,
    feed_post_id    BIGINT          NOT NULL UNIQUE REFERENCES feed_posts(id) ON DELETE CASCADE,
    mode            VARCHAR(255)    NOT NULL DEFAULT 'single'
                        CHECK (mode IN ('single', 'multiple')),
    max_choices     SMALLINT,
    closes_at       TIMESTAMP(0),
    secret          CHAR(64)        NOT NULL DEFAULT '',
    deleted_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

-- ----

CREATE TABLE poll_options (
    id          BIGSERIAL       PRIMARY KEY,
    poll_id     BIGINT          NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    text        VARCHAR(255)    NOT NULL,
    position    SMALLINT        NOT NULL DEFAULT 0,
    votes_count INTEGER         NOT NULL DEFAULT 0,
    deleted_at  TIMESTAMP(0),
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

CREATE INDEX poll_options_poll_id_position ON poll_options(poll_id, position);

-- ----

CREATE TABLE poll_votes (
    id          BIGSERIAL   PRIMARY KEY,
    poll_id     BIGINT      NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    option_id   BIGINT      NOT NULL REFERENCES poll_options(id) ON DELETE CASCADE,
    voter_hash  CHAR(64)    NOT NULL,   -- HMAC-SHA256(user_id, poll.secret)
    deleted_at  TIMESTAMP(0),
    created_at  TIMESTAMP(0)
);

CREATE UNIQUE INDEX poll_votes_poll_id_option_id_voter_hash_unique
    ON poll_votes(poll_id, option_id, voter_hash);
CREATE INDEX poll_votes_poll_id_voter_hash
    ON poll_votes(poll_id, voter_hash);

-- ============================================================
-- BOOKMARKS
-- ============================================================

CREATE TABLE bookmarks (
    id                      BIGSERIAL       PRIMARY KEY,
    user_id                 BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    bookmarkable_type       VARCHAR(255)    NOT NULL,   -- morphable class alias, e.g. 'feed_post'
    bookmarkable_id         BIGINT          NOT NULL,   -- ⚠ bigint: community_post UUID support requires migration to VARCHAR(36)
    snapshot_body           TEXT,
    snapshot_author_id      BIGINT,
    snapshot_author_name    VARCHAR(255),
    snapshot_is_whisper     BOOLEAN         NOT NULL DEFAULT false,
    snapshot_posted_at      TIMESTAMP(0)    NOT NULL,
    source_label            VARCHAR(255),
    original_deleted        BOOLEAN         NOT NULL DEFAULT false,
    created_at              TIMESTAMP(0),
    updated_at              TIMESTAMP(0)
);

CREATE UNIQUE INDEX bookmarks_user_id_bookmarkable_type_bookmarkable_id_unique
    ON bookmarks(user_id, bookmarkable_type, bookmarkable_id);
CREATE INDEX bookmarks_bookmarkable_type_bookmarkable_id
    ON bookmarks(bookmarkable_type, bookmarkable_id);
CREATE INDEX bookmarks_user_id_created_at
    ON bookmarks(user_id, created_at);

-- ----

CREATE TABLE bookmark_attachments (
    id              BIGSERIAL       PRIMARY KEY,
    bookmark_id     BIGINT          NOT NULL REFERENCES bookmarks(id) ON DELETE CASCADE,
    path            VARCHAR(255)    NOT NULL,
    thumbnail_path  VARCHAR(255),
    name            VARCHAR(255),
    mime            VARCHAR(255),
    size            INTEGER,
    position        SMALLINT        NOT NULL DEFAULT 1,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

-- ============================================================
-- CHAT / CONVERSATIONS
-- ============================================================

CREATE TABLE conversations (
    id          BIGSERIAL       PRIMARY KEY,
    type        VARCHAR(10)     NOT NULL DEFAULT 'direct',   -- 'direct' | 'group'
    user_a_id   BIGINT          REFERENCES users(id) ON DELETE CASCADE,   -- nullable for groups
    user_b_id   BIGINT          REFERENCES users(id) ON DELETE CASCADE,
    title       VARCHAR(60),
    avatar      VARCHAR(255),
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0)
);

CREATE UNIQUE INDEX conversations_user_a_id_user_b_id_unique ON conversations(user_a_id, user_b_id);
CREATE INDEX        conversations_type                        ON conversations(type);

-- ----

CREATE TABLE conversation_members (
    id              BIGSERIAL       PRIMARY KEY,
    conversation_id BIGINT          NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    user_id         BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role            VARCHAR(10)     NOT NULL DEFAULT 'member',   -- 'member' | 'admin' | 'owner'
    joined_at       TIMESTAMP(0)    NOT NULL,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX conversation_members_conversation_id_user_id_unique
    ON conversation_members(conversation_id, user_id);
CREATE INDEX conversation_members_conversation_id_role
    ON conversation_members(conversation_id, role);
CREATE INDEX conversation_members_user_id_conversation_id
    ON conversation_members(user_id, conversation_id);

-- ----

CREATE TABLE conversation_invites (
    id              BIGSERIAL       PRIMARY KEY,
    conversation_id BIGINT          NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    created_by_id   BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token           VARCHAR(64)     NOT NULL,
    type            VARCHAR(12)     NOT NULL,
    expires_at      TIMESTAMP(0),
    used_at         TIMESTAMP(0),
    revoked_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX conversation_invites_token_unique ON conversation_invites(token);
CREATE INDEX conversation_invites_token_revoked_at
    ON conversation_invites(token, revoked_at);
CREATE INDEX conversation_invites_conversation_id_type_revoked_at
    ON conversation_invites(conversation_id, type, revoked_at);

-- ----

CREATE TABLE conversation_join_requests (
    id              BIGSERIAL       PRIMARY KEY,
    conversation_id BIGINT          NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    invited_user_id BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    invited_by_id   BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status          VARCHAR(12)     NOT NULL DEFAULT 'pending',   -- 'pending' | 'accepted' | 'declined'
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE UNIQUE INDEX conversation_join_requests_conversation_id_invited_user_id_uniq
    ON conversation_join_requests(conversation_id, invited_user_id);
CREATE INDEX conversation_join_requests_invited_user_id_created_at
    ON conversation_join_requests(invited_user_id, created_at);
CREATE INDEX conversation_join_requests_invited_user_id_status_created_at
    ON conversation_join_requests(invited_user_id, status, created_at);

-- ----

CREATE TABLE messages (
    id                  BIGSERIAL       PRIMARY KEY,
    conversation_id     BIGINT          NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id           BIGINT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type                VARCHAR(12)     NOT NULL DEFAULT 'message',   -- 'message' | 'system' | 'file' | 'location'
    encrypted_payload   TEXT            NOT NULL,
    system_payload      JSON,
    reply_to_id         BIGINT          REFERENCES messages(id) ON DELETE SET NULL,
    delivered_at        TIMESTAMP(0),
    read_at             TIMESTAMP(0),
    edited_at           TIMESTAMP(0),
    expires_at          TIMESTAMP(0),
    deleted_at          TIMESTAMP(0),
    deleted_for         JSON,           -- array of user_ids who deleted locally
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0)
);

CREATE INDEX messages_conversation_id_created_at ON messages(conversation_id, created_at);
CREATE INDEX messages_sender_id                  ON messages(sender_id);
CREATE INDEX messages_expires_at                 ON messages(expires_at);
CREATE INDEX messages_type                       ON messages(type);

-- ----

CREATE TABLE message_edits (
    id                  BIGSERIAL   PRIMARY KEY,
    message_id          BIGINT      NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    encrypted_payload   TEXT        NOT NULL,   -- previous version
    created_at          TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
    -- no updated_at: immutable history
);

-- ----

CREATE TABLE pinned_messages (
    id              BIGSERIAL   PRIMARY KEY,
    conversation_id BIGINT      NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    message_id      BIGINT      NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    pinned_by_id    BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at      TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX pinned_messages_conversation_id_message_id_unique
    ON pinned_messages(conversation_id, message_id);

-- ----

CREATE TABLE chat_files (
    id              BIGSERIAL   PRIMARY KEY,
    uuid            UUID        NOT NULL,
    conversation_id BIGINT      NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id       BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    size_encrypted  BIGINT,
    storage_path    VARCHAR(255) NOT NULL,
    expires_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0)
);

-- ----

CREATE TABLE location_sessions (
    id                      BIGSERIAL   PRIMARY KEY,
    uuid                    UUID        NOT NULL,
    conversation_id         BIGINT      NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id               BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    duration_minutes        SMALLINT,
    last_encrypted_payload  TEXT,
    expires_at              TIMESTAMP(0),
    stopped_at              TIMESTAMP(0),
    created_at              TIMESTAMP(0),
    updated_at              TIMESTAMP(0)
);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (
    id              UUID            PRIMARY KEY,
    type            VARCHAR(255)    NOT NULL,
    notifiable_type VARCHAR(255)    NOT NULL,
    notifiable_id   BIGINT          NOT NULL,
    data            JSONB           NOT NULL,
    read_at         TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);

CREATE INDEX notifications_notifiable_type_notifiable_id
    ON notifications(notifiable_type, notifiable_id);

-- ============================================================
-- STATUS PAGE
-- ============================================================

CREATE TABLE status_incidents (
    id               BIGSERIAL       PRIMARY KEY,
    title            VARCHAR(255)    NOT NULL,
    component_id     VARCHAR(255),
    kind             VARCHAR(255)    NOT NULL DEFAULT 'warn'
                         CHECK (kind IN ('info', 'warn', 'crit')),
    status           VARCHAR(255)    NOT NULL DEFAULT 'ongoing'
                         CHECK (status IN ('ongoing', 'resolved')),
    body             TEXT,
    duration_minutes SMALLINT,
    started_at       TIMESTAMP(0)    NOT NULL,
    resolved_at      TIMESTAMP(0),
    created_at       TIMESTAMP(0),
    updated_at       TIMESTAMP(0)
);

-- ----

CREATE TABLE warrant_canaries (
    id           BIGSERIAL   PRIMARY KEY,
    signature    VARCHAR(64) NOT NULL,
    is_current   BOOLEAN     NOT NULL,
    published_at TIMESTAMP(0) NOT NULL,
    created_at   TIMESTAMP(0),
    updated_at   TIMESTAMP(0)
);

-- ============================================================
-- LARAVEL FRAMEWORK TABLES
-- ============================================================

CREATE TABLE sessions (
    id            VARCHAR(255) PRIMARY KEY,
    user_id       BIGINT,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    payload       TEXT         NOT NULL,
    last_activity INTEGER      NOT NULL
);

CREATE INDEX sessions_user_id          ON sessions(user_id);
CREATE INDEX sessions_last_activity    ON sessions(last_activity);

-- ----

CREATE TABLE cache (
    key         VARCHAR(255)    PRIMARY KEY,
    value       TEXT            NOT NULL,
    expiration  BIGINT          NOT NULL
);

CREATE TABLE cache_locks (
    key         VARCHAR(255)    PRIMARY KEY,
    owner       VARCHAR(255)    NOT NULL,
    expiration  BIGINT          NOT NULL
);

-- ----

CREATE TABLE jobs (
    id           BIGSERIAL   PRIMARY KEY,
    queue        VARCHAR(255) NOT NULL,
    payload      TEXT        NOT NULL,
    attempts     SMALLINT    NOT NULL,
    reserved_at  INTEGER,
    available_at INTEGER     NOT NULL,
    created_at   INTEGER     NOT NULL
);

CREATE INDEX jobs_queue ON jobs(queue);

-- ----

CREATE TABLE job_batches (
    id             VARCHAR(255)    PRIMARY KEY,
    name           VARCHAR(255)    NOT NULL,
    total_jobs     INTEGER         NOT NULL,
    pending_jobs   INTEGER         NOT NULL,
    failed_jobs    INTEGER         NOT NULL,
    failed_job_ids TEXT            NOT NULL,
    options        TEXT,
    cancelled_at   INTEGER,
    created_at     INTEGER         NOT NULL,
    finished_at    INTEGER
);

-- ----

CREATE TABLE failed_jobs (
    id          BIGSERIAL   PRIMARY KEY,
    uuid        VARCHAR(255) NOT NULL,
    connection  TEXT        NOT NULL,
    queue       TEXT        NOT NULL,
    payload     TEXT        NOT NULL,
    exception   TEXT        NOT NULL,
    failed_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX failed_jobs_uuid_unique ON failed_jobs(uuid);

-- ----

CREATE TABLE migrations (
    id          SERIAL      PRIMARY KEY,
    migration   VARCHAR(255) NOT NULL,
    batch       INTEGER     NOT NULL
);

-- ============================================================
-- TABLE SUMMARY (41 tables total)
-- ============================================================
--
-- AUTH / USERS (5)
--   users, user_keys, trusted_devices, login_history, push_subscriptions
--   password_reset_tokens, personal_access_tokens
--
-- PROFILE / PRIVACY (1)
--   profile_settings
--
-- SOCIAL (3)
--   friends, friend_codes, friend_requests
--
-- FEED (6)
--   feed_posts, feed_post_attachments, feed_votes
--   feed_comments, feed_comment_votes, feed_comment_edits
--
-- POLLS (3)
--   polls, poll_options, poll_votes
--
-- BOOKMARKS (2)
--   bookmarks, bookmark_attachments
--
-- CHAT (8)
--   conversations, conversation_members, conversation_invites,
--   conversation_join_requests, messages, message_edits,
--   pinned_messages, chat_files, location_sessions
--
-- NOTIFICATIONS (1)
--   notifications
--
-- STATUS (2)
--   status_incidents, warrant_canaries
--
-- FRAMEWORK (7)
--   sessions, cache, cache_locks, jobs, job_batches,
--   failed_jobs, migrations
--
-- ============================================================
-- KNOWN MIGRATION NEEDED FOR COMMUNITIES INTEGRATION
-- ============================================================
--
-- bookmarks.bookmarkable_id  BIGINT → VARCHAR(36)
--   Required to support community_posts with UUID primary keys.
--   On PostgreSQL:
--     ALTER TABLE bookmarks
--       ALTER COLUMN bookmarkable_id TYPE VARCHAR(36)
--       USING bookmarkable_id::VARCHAR;
--   Then recreate affected indexes.
