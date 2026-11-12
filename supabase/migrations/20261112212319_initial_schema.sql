create sequence "public"."account_links_id_seq";

create sequence "public"."activity_log_id_seq";

create sequence "public"."banners_id_seq";

create sequence "public"."cities_id_seq";

create sequence "public"."coupon_usages_id_seq";

create sequence "public"."coupons_id_seq";

create sequence "public"."device_tokens_id_seq";

create sequence "public"."driver_favorites_id_seq";

create sequence "public"."driver_profiles_id_seq";

create sequence "public"."exchange_rates_id_seq";

create sequence "public"."failed_jobs_id_seq";

create sequence "public"."jobs_id_seq";

create sequence "public"."migrations_id_seq";

create sequence "public"."permissions_id_seq";

create sequence "public"."personal_access_tokens_id_seq";

create sequence "public"."recharges_id_seq";

create sequence "public"."roles_id_seq";

create sequence "public"."settings_id_seq";

create sequence "public"."support_conversations_id_seq";

create sequence "public"."support_messages_id_seq";

create sequence "public"."trip_offers_id_seq";

create sequence "public"."trip_ratings_id_seq";

create sequence "public"."trips_id_seq";

create sequence "public"."user_documents_id_seq";

create sequence "public"."users_id_seq";

create sequence "public"."verification_reviews_id_seq";

create sequence "public"."wallet_transactions_id_seq";

create sequence "public"."wallets_id_seq";

create sequence "public"."withdrawals_id_seq";


  create table "public"."account_links" (
    "id" bigint not null default nextval('public.account_links_id_seq'::regclass),
    "user_id" bigint not null,
    "linked_user_id" bigint not null,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."activity_log" (
    "id" bigint not null default nextval('public.activity_log_id_seq'::regclass),
    "log_name" character varying(255),
    "description" text not null,
    "subject_type" character varying(255),
    "subject_id" bigint,
    "causer_type" character varying(255),
    "causer_id" bigint,
    "properties" json,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "event" character varying(255),
    "batch_uuid" uuid
      );



  create table "public"."banners" (
    "id" bigint not null default nextval('public.banners_id_seq'::regclass),
    "title" character varying(150) not null,
    "subtitle" character varying(255),
    "tag" character varying(50),
    "image_path" character varying(255),
    "image_url" character varying(255),
    "background_color" character varying(32) not null default '#310F38'::character varying,
    "background_color_end" character varying(32),
    "text_color" character varying(32) not null default '#FFFFFF'::character varying,
    "action_url" character varying(255),
    "action_text" character varying(50) default 'Ver más'::character varying,
    "target_role" character varying(20) not null default 'passenger'::character varying,
    "city_id" bigint,
    "is_active" boolean not null default true,
    "priority" integer not null default 0,
    "starts_at" timestamp(0) without time zone,
    "expires_at" timestamp(0) without time zone,
    "created_by" bigint,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."cache" (
    "key" character varying(255) not null,
    "value" text not null,
    "expiration" integer not null
      );



  create table "public"."cache_locks" (
    "key" character varying(255) not null,
    "owner" character varying(255) not null,
    "expiration" integer not null
      );



  create table "public"."cities" (
    "id" bigint not null default nextval('public.cities_id_seq'::regclass),
    "name" character varying(80) not null,
    "slug" character varying(80) not null,
    "state" character varying(80) not null,
    "lat" numeric(10,7) not null,
    "lng" numeric(10,7) not null,
    "is_active" boolean not null default true,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."coupon_usages" (
    "id" bigint not null default nextval('public.coupon_usages_id_seq'::regclass),
    "coupon_id" bigint not null,
    "user_id" bigint not null,
    "trip_id" bigint,
    "used_at" timestamp(0) without time zone not null
      );



  create table "public"."coupons" (
    "id" bigint not null default nextval('public.coupons_id_seq'::regclass),
    "code" character varying(32) not null,
    "discount_percent" numeric(5,2) not null,
    "min_purchase" numeric(10,2),
    "max_discount" numeric(10,2),
    "valid_from" date not null,
    "valid_until" date,
    "max_uses_global" integer,
    "max_uses_per_user" integer,
    "city_id" bigint,
    "created_by" bigint not null,
    "is_active" boolean not null default true,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."device_tokens" (
    "id" bigint not null default nextval('public.device_tokens_id_seq'::regclass),
    "user_id" bigint not null,
    "token" character varying(512) not null,
    "platform" character varying(20) not null default 'android'::character varying,
    "device_name" character varying(120),
    "last_used_at" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."driver_favorites" (
    "id" bigint not null default nextval('public.driver_favorites_id_seq'::regclass),
    "passenger_id" bigint not null,
    "driver_id" bigint not null,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."driver_profiles" (
    "id" bigint not null default nextval('public.driver_profiles_id_seq'::regclass),
    "user_id" bigint not null,
    "vehicle_brand" character varying(255) not null,
    "vehicle_model" character varying(255) not null,
    "vehicle_year" smallint not null,
    "vehicle_plate" character varying(12) not null,
    "is_online" boolean not null default false,
    "went_online_at" timestamp(0) without time zone,
    "last_location" extensions.geography(Point,4326),
    "last_speed_kmh" numeric(5,2),
    "last_bearing" smallint,
    "last_location_at" timestamp(0) without time zone,
    "rating_average" numeric(3,2) not null default '0'::numeric,
    "trips_completed" integer not null default 0,
    "trips_rejected" integer not null default 0,
    "rejections_in_window" smallint not null default '0'::smallint,
    "rejection_window_started_at" timestamp(0) without time zone,
    "blocked_until" timestamp(0) without time zone,
    "blocked_reason" character varying(255),
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "vehicle_type" character varying(12) not null default 'moto'::character varying,
    "ratings_count" integer not null default 0,
    "city_id" bigint
      );



  create table "public"."exchange_rates" (
    "id" bigint not null default nextval('public.exchange_rates_id_seq'::regclass),
    "rate" numeric(14,4) not null,
    "effective_date" date not null,
    "created_by" bigint,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."failed_jobs" (
    "id" bigint not null default nextval('public.failed_jobs_id_seq'::regclass),
    "uuid" character varying(255) not null,
    "connection" text not null,
    "queue" text not null,
    "payload" text not null,
    "exception" text not null,
    "failed_at" timestamp(0) without time zone not null default CURRENT_TIMESTAMP
      );



  create table "public"."job_batches" (
    "id" character varying(255) not null,
    "name" character varying(255) not null,
    "total_jobs" integer not null,
    "pending_jobs" integer not null,
    "failed_jobs" integer not null,
    "failed_job_ids" text not null,
    "options" text,
    "cancelled_at" integer,
    "created_at" integer not null,
    "finished_at" integer
      );



  create table "public"."jobs" (
    "id" bigint not null default nextval('public.jobs_id_seq'::regclass),
    "queue" character varying(255) not null,
    "payload" text not null,
    "attempts" smallint not null,
    "reserved_at" integer,
    "available_at" integer not null,
    "created_at" integer not null
      );



  create table "public"."migrations" (
    "id" integer not null default nextval('public.migrations_id_seq'::regclass),
    "migration" character varying(255) not null,
    "batch" integer not null
      );



  create table "public"."model_has_permissions" (
    "permission_id" bigint not null,
    "model_type" character varying(255) not null,
    "model_id" bigint not null
      );



  create table "public"."model_has_roles" (
    "role_id" bigint not null,
    "model_type" character varying(255) not null,
    "model_id" bigint not null
      );



  create table "public"."notifications" (
    "id" uuid not null,
    "type" character varying(255) not null,
    "notifiable_type" character varying(255) not null,
    "notifiable_id" bigint not null,
    "data" text not null,
    "read_at" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."password_reset_tokens" (
    "email" character varying(255) not null,
    "token" character varying(255) not null,
    "created_at" timestamp(0) without time zone
      );



  create table "public"."permissions" (
    "id" bigint not null default nextval('public.permissions_id_seq'::regclass),
    "name" character varying(255) not null,
    "guard_name" character varying(255) not null,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."personal_access_tokens" (
    "id" bigint not null default nextval('public.personal_access_tokens_id_seq'::regclass),
    "tokenable_type" character varying(255) not null,
    "tokenable_id" bigint not null,
    "name" text not null,
    "token" character varying(64) not null,
    "abilities" text,
    "last_used_at" timestamp(0) without time zone,
    "expires_at" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."recharges" (
    "id" bigint not null default nextval('public.recharges_id_seq'::regclass),
    "user_id" bigint not null,
    "amount_bs" numeric(14,2) not null,
    "exchange_rate" numeric(14,4),
    "amount_usd" numeric(12,2),
    "bank" character varying(80) not null,
    "reference" character varying(60) not null,
    "transferred_at" date not null,
    "proof_path" character varying(255) not null,
    "proof_disk" character varying(20) not null default 'local'::character varying,
    "proof_original_name" character varying(255),
    "proof_mime_type" character varying(100),
    "proof_size_bytes" bigint,
    "status" character varying(20) not null default 'pending'::character varying,
    "reviewed_by" bigint,
    "reviewed_at" timestamp(0) without time zone,
    "rejection_reason" text,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."role_has_permissions" (
    "permission_id" bigint not null,
    "role_id" bigint not null
      );



  create table "public"."roles" (
    "id" bigint not null default nextval('public.roles_id_seq'::regclass),
    "name" character varying(255) not null,
    "guard_name" character varying(255) not null,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."sessions" (
    "id" character varying(255) not null,
    "user_id" bigint,
    "ip_address" character varying(45),
    "user_agent" text,
    "payload" text not null,
    "last_activity" integer not null
      );



  create table "public"."settings" (
    "id" bigint not null default nextval('public.settings_id_seq'::regclass),
    "key" character varying(100) not null,
    "value" text,
    "type" character varying(20) not null default 'string'::character varying,
    "group" character varying(40) not null default 'general'::character varying,
    "label" character varying(160) not null,
    "description" character varying(255),
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "city_id" bigint
      );



  create table "public"."support_conversations" (
    "id" bigint not null default nextval('public.support_conversations_id_seq'::regclass),
    "user_id" bigint not null,
    "status" character varying(20) not null default 'open'::character varying,
    "last_message_at" timestamp(0) without time zone,
    "closed_at" timestamp(0) without time zone,
    "closed_by" bigint,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."support_messages" (
    "id" bigint not null default nextval('public.support_messages_id_seq'::regclass),
    "support_conversation_id" bigint not null,
    "sender_id" bigint,
    "sender_type" character varying(20) not null,
    "body" text not null,
    "read_at" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."trip_offers" (
    "id" bigint not null default nextval('public.trip_offers_id_seq'::regclass),
    "trip_id" bigint not null,
    "driver_id" bigint not null,
    "status" character varying(16) not null default 'pending'::character varying,
    "distance_to_pickup_km" numeric(8,2) not null,
    "offered_at" timestamp(0) without time zone not null,
    "expires_at" timestamp(0) without time zone not null,
    "responded_at" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."trip_ratings" (
    "id" bigint not null default nextval('public.trip_ratings_id_seq'::regclass),
    "trip_id" bigint not null,
    "passenger_id" bigint not null,
    "driver_id" bigint not null,
    "rating" smallint not null,
    "comment" character varying(500),
    "created_at" timestamp(0) without time zone
      );



  create table "public"."trips" (
    "id" bigint not null default nextval('public.trips_id_seq'::regclass),
    "passenger_id" bigint not null,
    "driver_id" bigint,
    "status" character varying(20) not null default 'searching'::character varying,
    "payment_method" character varying(12) not null,
    "origin_address" character varying(255) not null,
    "destination_address" character varying(255) not null,
    "origin_lat" numeric(10,7) not null,
    "origin_lng" numeric(10,7) not null,
    "destination_lat" numeric(10,7) not null,
    "destination_lng" numeric(10,7) not null,
    "distance_km" numeric(8,2) not null,
    "duration_minutes" integer not null,
    "base_fare" numeric(8,2) not null,
    "price_per_km" numeric(8,2) not null,
    "price_per_minute" numeric(8,2) not null,
    "minimum_fare" numeric(8,2) not null,
    "minimum_applied" boolean not null default false,
    "dynamic_multiplier" numeric(4,2) not null,
    "night_surcharge_percent" numeric(5,2) not null,
    "subtotal" numeric(10,2) not null,
    "total" numeric(10,2) not null,
    "commission_percent" numeric(5,2) not null,
    "commission_amount" numeric(10,2) not null,
    "driver_earning" numeric(10,2) not null,
    "exchange_rate" numeric(12,4),
    "fare_breakdown" json not null,
    "wallet_hold_amount" numeric(10,2) not null default '0'::numeric,
    "search_radius_index" smallint not null default '0'::smallint,
    "requested_at" timestamp(0) without time zone not null,
    "accepted_at" timestamp(0) without time zone,
    "arrived_at" timestamp(0) without time zone,
    "started_at" timestamp(0) without time zone,
    "completed_at" timestamp(0) without time zone,
    "cancelled_at" timestamp(0) without time zone,
    "cancelled_by" character varying(12),
    "cancellation_reason" character varying(255),
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "vehicle_type" character varying(12) not null default 'moto'::character varying,
    "city_id" bigint,
    "coupon_id" bigint,
    "discount_percent" numeric(5,2),
    "discount_amount" numeric(10,2) not null default '0'::numeric,
    "subsidy_amount" numeric(10,2) not null default '0'::numeric,
    "promo_type" character varying(20)
      );



  create table "public"."user_documents" (
    "id" bigint not null default nextval('public.user_documents_id_seq'::regclass),
    "user_id" bigint not null,
    "type" character varying(30) not null,
    "status" character varying(20) not null default 'pending'::character varying,
    "file_path" character varying(255) not null,
    "disk" character varying(30) not null default 'local'::character varying,
    "original_name" character varying(255),
    "mime_type" character varying(100),
    "size_bytes" bigint,
    "expires_at" date,
    "uploaded_at" timestamp(0) without time zone,
    "reviewed_by" bigint,
    "reviewed_at" timestamp(0) without time zone,
    "rejection_reason" text,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "expiry_notice_days" smallint
      );



  create table "public"."users" (
    "id" bigint not null default nextval('public.users_id_seq'::regclass),
    "role" character varying(20) not null default 'passenger'::character varying,
    "first_name" character varying(255) not null,
    "last_name" character varying(255) not null,
    "national_id" character varying(20) not null,
    "email" character varying(255) not null,
    "phone" character varying(25),
    "address" character varying(255) not null,
    "email_verified_at" timestamp(0) without time zone,
    "password" character varying(255) not null,
    "remember_token" character varying(100),
    "status" character varying(20) not null default 'pending'::character varying,
    "status_reason" text,
    "approved_at" timestamp(0) without time zone,
    "reviewed_by" bigint,
    "submitted_at" timestamp(0) without time zone,
    "blocked_until" timestamp(0) without time zone,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "deleted_at" timestamp(0) without time zone,
    "two_factor_secret" text,
    "two_factor_recovery_codes" text,
    "two_factor_confirmed_at" timestamp(0) without time zone,
    "terms_accepted_at" timestamp(0) without time zone,
    "terms_version" character varying(20),
    "city_id" bigint,
    "is_owner" boolean not null default false,
    "avatar" character varying(255)
      );



  create table "public"."verification_reviews" (
    "id" bigint not null default nextval('public.verification_reviews_id_seq'::regclass),
    "user_id" bigint not null,
    "auditor_id" bigint,
    "scope" character varying(20) not null default 'document'::character varying,
    "document_id" bigint,
    "document_type" character varying(30),
    "document_path" character varying(255),
    "decision" character varying(20) not null,
    "reason" text,
    "ip_address" character varying(45),
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."wallet_transactions" (
    "id" bigint not null default nextval('public.wallet_transactions_id_seq'::regclass),
    "wallet_id" bigint not null,
    "type" character varying(30) not null,
    "direction" character varying(6) not null,
    "amount" numeric(12,2) not null,
    "balance_after" numeric(12,2) not null,
    "held_after" numeric(12,2) not null default '0'::numeric,
    "description" character varying(255),
    "reference_type" character varying(255),
    "reference_id" bigint,
    "created_by" bigint,
    "metadata" json,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."wallets" (
    "id" bigint not null default nextval('public.wallets_id_seq'::regclass),
    "user_id" bigint not null,
    "balance" numeric(12,2) not null default '0'::numeric,
    "held_balance" numeric(12,2) not null default '0'::numeric,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );



  create table "public"."withdrawals" (
    "id" bigint not null default nextval('public.withdrawals_id_seq'::regclass),
    "user_id" bigint not null,
    "amount_usd" numeric(12,2) not null,
    "bank" character varying(80) not null,
    "account_number" character varying(40) not null,
    "account_holder" character varying(120) not null,
    "account_id" character varying(20),
    "status" character varying(20) not null default 'pending'::character varying,
    "proof_path" character varying(255),
    "proof_disk" character varying(20) not null default 'local'::character varying,
    "proof_original_name" character varying(255),
    "proof_mime_type" character varying(100),
    "proof_size_bytes" bigint,
    "reviewed_by" bigint,
    "reviewed_at" timestamp(0) without time zone,
    "rejection_reason" text,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone
      );


alter sequence "public"."account_links_id_seq" owned by "public"."account_links"."id";

alter sequence "public"."activity_log_id_seq" owned by "public"."activity_log"."id";

alter sequence "public"."banners_id_seq" owned by "public"."banners"."id";

alter sequence "public"."cities_id_seq" owned by "public"."cities"."id";

alter sequence "public"."coupon_usages_id_seq" owned by "public"."coupon_usages"."id";

alter sequence "public"."coupons_id_seq" owned by "public"."coupons"."id";

alter sequence "public"."device_tokens_id_seq" owned by "public"."device_tokens"."id";

alter sequence "public"."driver_favorites_id_seq" owned by "public"."driver_favorites"."id";

alter sequence "public"."driver_profiles_id_seq" owned by "public"."driver_profiles"."id";

alter sequence "public"."exchange_rates_id_seq" owned by "public"."exchange_rates"."id";

alter sequence "public"."failed_jobs_id_seq" owned by "public"."failed_jobs"."id";

alter sequence "public"."jobs_id_seq" owned by "public"."jobs"."id";

alter sequence "public"."migrations_id_seq" owned by "public"."migrations"."id";

alter sequence "public"."permissions_id_seq" owned by "public"."permissions"."id";

alter sequence "public"."personal_access_tokens_id_seq" owned by "public"."personal_access_tokens"."id";

alter sequence "public"."recharges_id_seq" owned by "public"."recharges"."id";

alter sequence "public"."roles_id_seq" owned by "public"."roles"."id";

alter sequence "public"."settings_id_seq" owned by "public"."settings"."id";

alter sequence "public"."support_conversations_id_seq" owned by "public"."support_conversations"."id";

alter sequence "public"."support_messages_id_seq" owned by "public"."support_messages"."id";

alter sequence "public"."trip_offers_id_seq" owned by "public"."trip_offers"."id";

alter sequence "public"."trip_ratings_id_seq" owned by "public"."trip_ratings"."id";

alter sequence "public"."trips_id_seq" owned by "public"."trips"."id";

alter sequence "public"."user_documents_id_seq" owned by "public"."user_documents"."id";

alter sequence "public"."users_id_seq" owned by "public"."users"."id";

alter sequence "public"."verification_reviews_id_seq" owned by "public"."verification_reviews"."id";

alter sequence "public"."wallet_transactions_id_seq" owned by "public"."wallet_transactions"."id";

alter sequence "public"."wallets_id_seq" owned by "public"."wallets"."id";

alter sequence "public"."withdrawals_id_seq" owned by "public"."withdrawals"."id";

CREATE UNIQUE INDEX account_links_pkey ON public.account_links USING btree (id);

CREATE UNIQUE INDEX account_links_user_id_linked_user_id_unique ON public.account_links USING btree (user_id, linked_user_id);

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);

CREATE UNIQUE INDEX activity_log_pkey ON public.activity_log USING btree (id);

CREATE INDEX banners_is_active_target_role_priority_index ON public.banners USING btree (is_active, target_role, priority);

CREATE UNIQUE INDEX banners_pkey ON public.banners USING btree (id);

CREATE INDEX banners_starts_at_expires_at_index ON public.banners USING btree (starts_at, expires_at);

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);

CREATE UNIQUE INDEX cache_locks_pkey ON public.cache_locks USING btree (key);

CREATE UNIQUE INDEX cache_pkey ON public.cache USING btree (key);

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);

CREATE INDEX cities_is_active_index ON public.cities USING btree (is_active);

CREATE UNIQUE INDEX cities_pkey ON public.cities USING btree (id);

CREATE UNIQUE INDEX cities_slug_unique ON public.cities USING btree (slug);

CREATE INDEX coupon_usages_coupon_id_user_id_index ON public.coupon_usages USING btree (coupon_id, user_id);

CREATE UNIQUE INDEX coupon_usages_pkey ON public.coupon_usages USING btree (id);

CREATE INDEX coupon_usages_trip_id_index ON public.coupon_usages USING btree (trip_id);

CREATE UNIQUE INDEX coupons_code_unique ON public.coupons USING btree (code);

CREATE INDEX coupons_is_active_valid_from_valid_until_index ON public.coupons USING btree (is_active, valid_from, valid_until);

CREATE UNIQUE INDEX coupons_pkey ON public.coupons USING btree (id);

CREATE UNIQUE INDEX device_tokens_pkey ON public.device_tokens USING btree (id);

CREATE UNIQUE INDEX device_tokens_token_unique ON public.device_tokens USING btree (token);

CREATE INDEX device_tokens_user_id_platform_index ON public.device_tokens USING btree (user_id, platform);

CREATE INDEX driver_favorites_driver_id_index ON public.driver_favorites USING btree (driver_id);

CREATE UNIQUE INDEX driver_favorites_passenger_id_driver_id_unique ON public.driver_favorites USING btree (passenger_id, driver_id);

CREATE UNIQUE INDEX driver_favorites_pkey ON public.driver_favorites USING btree (id);

CREATE INDEX driver_profiles_is_online_blocked_until_index ON public.driver_profiles USING btree (is_online, blocked_until);

CREATE UNIQUE INDEX driver_profiles_pkey ON public.driver_profiles USING btree (id);

CREATE UNIQUE INDEX driver_profiles_user_id_unique ON public.driver_profiles USING btree (user_id);

CREATE UNIQUE INDEX driver_profiles_vehicle_plate_unique ON public.driver_profiles USING btree (vehicle_plate);

CREATE INDEX driver_profiles_vehicle_type_index ON public.driver_profiles USING btree (vehicle_type);

CREATE INDEX exchange_rates_effective_date_index ON public.exchange_rates USING btree (effective_date);

CREATE UNIQUE INDEX exchange_rates_pkey ON public.exchange_rates USING btree (id);

CREATE UNIQUE INDEX failed_jobs_pkey ON public.failed_jobs USING btree (id);

CREATE UNIQUE INDEX failed_jobs_uuid_unique ON public.failed_jobs USING btree (uuid);

CREATE UNIQUE INDEX job_batches_pkey ON public.job_batches USING btree (id);

CREATE UNIQUE INDEX jobs_pkey ON public.jobs USING btree (id);

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);

CREATE UNIQUE INDEX migrations_pkey ON public.migrations USING btree (id);

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);

CREATE UNIQUE INDEX model_has_permissions_pkey ON public.model_has_permissions USING btree (permission_id, model_id, model_type);

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);

CREATE UNIQUE INDEX model_has_roles_pkey ON public.model_has_roles USING btree (role_id, model_id, model_type);

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);

CREATE INDEX notifications_notifiable_type_notifiable_id_read_at_index ON public.notifications USING btree (notifiable_type, notifiable_id, read_at);

CREATE UNIQUE INDEX notifications_pkey ON public.notifications USING btree (id);

CREATE UNIQUE INDEX password_reset_tokens_pkey ON public.password_reset_tokens USING btree (email);

CREATE UNIQUE INDEX permissions_name_guard_name_unique ON public.permissions USING btree (name, guard_name);

CREATE UNIQUE INDEX permissions_pkey ON public.permissions USING btree (id);

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);

CREATE UNIQUE INDEX personal_access_tokens_pkey ON public.personal_access_tokens USING btree (id);

CREATE UNIQUE INDEX personal_access_tokens_token_unique ON public.personal_access_tokens USING btree (token);

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);

CREATE INDEX recharges_bank_reference_index ON public.recharges USING btree (bank, reference);

CREATE UNIQUE INDEX recharges_pkey ON public.recharges USING btree (id);

CREATE INDEX recharges_status_index ON public.recharges USING btree (status);

CREATE INDEX recharges_user_id_created_at_index ON public.recharges USING btree (user_id, created_at);

CREATE UNIQUE INDEX role_has_permissions_pkey ON public.role_has_permissions USING btree (permission_id, role_id);

CREATE UNIQUE INDEX roles_name_guard_name_unique ON public.roles USING btree (name, guard_name);

CREATE UNIQUE INDEX roles_pkey ON public.roles USING btree (id);

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);

CREATE UNIQUE INDEX sessions_pkey ON public.sessions USING btree (id);

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

CREATE UNIQUE INDEX settings_city_id_key_unique ON public.settings USING btree (city_id, key);

CREATE INDEX settings_group_index ON public.settings USING btree ("group");

CREATE UNIQUE INDEX settings_pkey ON public.settings USING btree (id);

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);

CREATE UNIQUE INDEX support_conversations_pkey ON public.support_conversations USING btree (id);

CREATE INDEX support_conversations_status_last_message_at_index ON public.support_conversations USING btree (status, last_message_at);

CREATE INDEX support_conversations_user_id_status_index ON public.support_conversations USING btree (user_id, status);

CREATE UNIQUE INDEX support_messages_pkey ON public.support_messages USING btree (id);

CREATE INDEX support_messages_sender_type_read_at_index ON public.support_messages USING btree (sender_type, read_at);

CREATE INDEX support_messages_support_conversation_id_id_index ON public.support_messages USING btree (support_conversation_id, id);

CREATE INDEX trip_offers_driver_id_status_index ON public.trip_offers USING btree (driver_id, status);

CREATE UNIQUE INDEX trip_offers_pkey ON public.trip_offers USING btree (id);

CREATE INDEX trip_offers_status_expires_at_index ON public.trip_offers USING btree (status, expires_at);

CREATE UNIQUE INDEX trip_offers_trip_id_driver_id_unique ON public.trip_offers USING btree (trip_id, driver_id);

CREATE INDEX trip_ratings_driver_id_created_at_index ON public.trip_ratings USING btree (driver_id, created_at);

CREATE UNIQUE INDEX trip_ratings_pkey ON public.trip_ratings USING btree (id);

CREATE UNIQUE INDEX trip_ratings_trip_id_unique ON public.trip_ratings USING btree (trip_id);

CREATE INDEX trips_city_id_status_index ON public.trips USING btree (city_id, status);

CREATE INDEX trips_coupon_id_index ON public.trips USING btree (coupon_id);

CREATE INDEX trips_driver_id_status_index ON public.trips USING btree (driver_id, status);

CREATE INDEX trips_passenger_id_status_index ON public.trips USING btree (passenger_id, status);

CREATE UNIQUE INDEX trips_pkey ON public.trips USING btree (id);

CREATE INDEX trips_promo_type_index ON public.trips USING btree (promo_type);

CREATE INDEX trips_status_index ON public.trips USING btree (status);

CREATE INDEX trips_status_requested_at_index ON public.trips USING btree (status, requested_at);

CREATE INDEX trips_vehicle_type_index ON public.trips USING btree (vehicle_type);

CREATE INDEX user_documents_expires_at_index ON public.user_documents USING btree (expires_at);

CREATE UNIQUE INDEX user_documents_pkey ON public.user_documents USING btree (id);

CREATE INDEX user_documents_status_uploaded_at_index ON public.user_documents USING btree (status, uploaded_at);

CREATE UNIQUE INDEX user_documents_user_id_type_unique ON public.user_documents USING btree (user_id, type);

CREATE UNIQUE INDEX users_email_role_unique ON public.users USING btree (email, role);

CREATE UNIQUE INDEX users_is_owner_unique ON public.users USING btree (is_owner) WHERE ((is_owner = true) AND (deleted_at IS NULL));

CREATE UNIQUE INDEX users_national_id_role_unique ON public.users USING btree (national_id, role);

CREATE UNIQUE INDEX users_pkey ON public.users USING btree (id);

CREATE INDEX users_role_index ON public.users USING btree (role);

CREATE INDEX users_status_index ON public.users USING btree (status);

CREATE UNIQUE INDEX verification_reviews_pkey ON public.verification_reviews USING btree (id);

CREATE INDEX verification_reviews_user_id_created_at_index ON public.verification_reviews USING btree (user_id, created_at);

CREATE UNIQUE INDEX wallet_transactions_pkey ON public.wallet_transactions USING btree (id);

CREATE INDEX wallet_transactions_reference_type_reference_id_index ON public.wallet_transactions USING btree (reference_type, reference_id);

CREATE INDEX wallet_transactions_type_index ON public.wallet_transactions USING btree (type);

CREATE INDEX wallet_transactions_wallet_id_created_at_index ON public.wallet_transactions USING btree (wallet_id, created_at);

CREATE UNIQUE INDEX wallets_pkey ON public.wallets USING btree (id);

CREATE UNIQUE INDEX wallets_user_id_unique ON public.wallets USING btree (user_id);

CREATE UNIQUE INDEX withdrawals_pkey ON public.withdrawals USING btree (id);

CREATE INDEX withdrawals_status_index ON public.withdrawals USING btree (status);

CREATE INDEX withdrawals_user_id_created_at_index ON public.withdrawals USING btree (user_id, created_at);

alter table "public"."account_links" add constraint "account_links_pkey" PRIMARY KEY using index "account_links_pkey";

alter table "public"."activity_log" add constraint "activity_log_pkey" PRIMARY KEY using index "activity_log_pkey";

alter table "public"."banners" add constraint "banners_pkey" PRIMARY KEY using index "banners_pkey";

alter table "public"."cache" add constraint "cache_pkey" PRIMARY KEY using index "cache_pkey";

alter table "public"."cache_locks" add constraint "cache_locks_pkey" PRIMARY KEY using index "cache_locks_pkey";

alter table "public"."cities" add constraint "cities_pkey" PRIMARY KEY using index "cities_pkey";

alter table "public"."coupon_usages" add constraint "coupon_usages_pkey" PRIMARY KEY using index "coupon_usages_pkey";

alter table "public"."coupons" add constraint "coupons_pkey" PRIMARY KEY using index "coupons_pkey";

alter table "public"."device_tokens" add constraint "device_tokens_pkey" PRIMARY KEY using index "device_tokens_pkey";

alter table "public"."driver_favorites" add constraint "driver_favorites_pkey" PRIMARY KEY using index "driver_favorites_pkey";

alter table "public"."driver_profiles" add constraint "driver_profiles_pkey" PRIMARY KEY using index "driver_profiles_pkey";

alter table "public"."exchange_rates" add constraint "exchange_rates_pkey" PRIMARY KEY using index "exchange_rates_pkey";

alter table "public"."failed_jobs" add constraint "failed_jobs_pkey" PRIMARY KEY using index "failed_jobs_pkey";

alter table "public"."job_batches" add constraint "job_batches_pkey" PRIMARY KEY using index "job_batches_pkey";

alter table "public"."jobs" add constraint "jobs_pkey" PRIMARY KEY using index "jobs_pkey";

alter table "public"."migrations" add constraint "migrations_pkey" PRIMARY KEY using index "migrations_pkey";

alter table "public"."model_has_permissions" add constraint "model_has_permissions_pkey" PRIMARY KEY using index "model_has_permissions_pkey";

alter table "public"."model_has_roles" add constraint "model_has_roles_pkey" PRIMARY KEY using index "model_has_roles_pkey";

alter table "public"."notifications" add constraint "notifications_pkey" PRIMARY KEY using index "notifications_pkey";

alter table "public"."password_reset_tokens" add constraint "password_reset_tokens_pkey" PRIMARY KEY using index "password_reset_tokens_pkey";

alter table "public"."permissions" add constraint "permissions_pkey" PRIMARY KEY using index "permissions_pkey";

alter table "public"."personal_access_tokens" add constraint "personal_access_tokens_pkey" PRIMARY KEY using index "personal_access_tokens_pkey";

alter table "public"."recharges" add constraint "recharges_pkey" PRIMARY KEY using index "recharges_pkey";

alter table "public"."role_has_permissions" add constraint "role_has_permissions_pkey" PRIMARY KEY using index "role_has_permissions_pkey";

alter table "public"."roles" add constraint "roles_pkey" PRIMARY KEY using index "roles_pkey";

alter table "public"."sessions" add constraint "sessions_pkey" PRIMARY KEY using index "sessions_pkey";

alter table "public"."settings" add constraint "settings_pkey" PRIMARY KEY using index "settings_pkey";

alter table "public"."support_conversations" add constraint "support_conversations_pkey" PRIMARY KEY using index "support_conversations_pkey";

alter table "public"."support_messages" add constraint "support_messages_pkey" PRIMARY KEY using index "support_messages_pkey";

alter table "public"."trip_offers" add constraint "trip_offers_pkey" PRIMARY KEY using index "trip_offers_pkey";

alter table "public"."trip_ratings" add constraint "trip_ratings_pkey" PRIMARY KEY using index "trip_ratings_pkey";

alter table "public"."trips" add constraint "trips_pkey" PRIMARY KEY using index "trips_pkey";

alter table "public"."user_documents" add constraint "user_documents_pkey" PRIMARY KEY using index "user_documents_pkey";

alter table "public"."users" add constraint "users_pkey" PRIMARY KEY using index "users_pkey";

alter table "public"."verification_reviews" add constraint "verification_reviews_pkey" PRIMARY KEY using index "verification_reviews_pkey";

alter table "public"."wallet_transactions" add constraint "wallet_transactions_pkey" PRIMARY KEY using index "wallet_transactions_pkey";

alter table "public"."wallets" add constraint "wallets_pkey" PRIMARY KEY using index "wallets_pkey";

alter table "public"."withdrawals" add constraint "withdrawals_pkey" PRIMARY KEY using index "withdrawals_pkey";

alter table "public"."account_links" add constraint "account_links_linked_user_id_foreign" FOREIGN KEY (linked_user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."account_links" validate constraint "account_links_linked_user_id_foreign";

alter table "public"."account_links" add constraint "account_links_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."account_links" validate constraint "account_links_user_id_foreign";

alter table "public"."account_links" add constraint "account_links_user_id_linked_user_id_unique" UNIQUE using index "account_links_user_id_linked_user_id_unique";

alter table "public"."banners" add constraint "banners_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL not valid;

alter table "public"."banners" validate constraint "banners_city_id_foreign";

alter table "public"."banners" add constraint "banners_created_by_foreign" FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."banners" validate constraint "banners_created_by_foreign";

alter table "public"."cities" add constraint "cities_slug_unique" UNIQUE using index "cities_slug_unique";

alter table "public"."coupon_usages" add constraint "coupon_usages_coupon_id_foreign" FOREIGN KEY (coupon_id) REFERENCES public.coupons(id) ON DELETE CASCADE not valid;

alter table "public"."coupon_usages" validate constraint "coupon_usages_coupon_id_foreign";

alter table "public"."coupon_usages" add constraint "coupon_usages_trip_id_foreign" FOREIGN KEY (trip_id) REFERENCES public.trips(id) ON DELETE SET NULL not valid;

alter table "public"."coupon_usages" validate constraint "coupon_usages_trip_id_foreign";

alter table "public"."coupon_usages" add constraint "coupon_usages_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."coupon_usages" validate constraint "coupon_usages_user_id_foreign";

alter table "public"."coupons" add constraint "coupons_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL not valid;

alter table "public"."coupons" validate constraint "coupons_city_id_foreign";

alter table "public"."coupons" add constraint "coupons_code_unique" UNIQUE using index "coupons_code_unique";

alter table "public"."coupons" add constraint "coupons_created_by_foreign" FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."coupons" validate constraint "coupons_created_by_foreign";

alter table "public"."device_tokens" add constraint "device_tokens_token_unique" UNIQUE using index "device_tokens_token_unique";

alter table "public"."device_tokens" add constraint "device_tokens_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."device_tokens" validate constraint "device_tokens_user_id_foreign";

alter table "public"."driver_favorites" add constraint "driver_favorites_driver_id_foreign" FOREIGN KEY (driver_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."driver_favorites" validate constraint "driver_favorites_driver_id_foreign";

alter table "public"."driver_favorites" add constraint "driver_favorites_passenger_id_driver_id_unique" UNIQUE using index "driver_favorites_passenger_id_driver_id_unique";

alter table "public"."driver_favorites" add constraint "driver_favorites_passenger_id_foreign" FOREIGN KEY (passenger_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."driver_favorites" validate constraint "driver_favorites_passenger_id_foreign";

alter table "public"."driver_profiles" add constraint "driver_profiles_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL not valid;

alter table "public"."driver_profiles" validate constraint "driver_profiles_city_id_foreign";

alter table "public"."driver_profiles" add constraint "driver_profiles_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."driver_profiles" validate constraint "driver_profiles_user_id_foreign";

alter table "public"."driver_profiles" add constraint "driver_profiles_user_id_unique" UNIQUE using index "driver_profiles_user_id_unique";

alter table "public"."driver_profiles" add constraint "driver_profiles_vehicle_plate_unique" UNIQUE using index "driver_profiles_vehicle_plate_unique";

alter table "public"."exchange_rates" add constraint "exchange_rates_created_by_foreign" FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."exchange_rates" validate constraint "exchange_rates_created_by_foreign";

alter table "public"."failed_jobs" add constraint "failed_jobs_uuid_unique" UNIQUE using index "failed_jobs_uuid_unique";

alter table "public"."model_has_permissions" add constraint "model_has_permissions_permission_id_foreign" FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE not valid;

alter table "public"."model_has_permissions" validate constraint "model_has_permissions_permission_id_foreign";

alter table "public"."model_has_roles" add constraint "model_has_roles_role_id_foreign" FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE not valid;

alter table "public"."model_has_roles" validate constraint "model_has_roles_role_id_foreign";

alter table "public"."permissions" add constraint "permissions_name_guard_name_unique" UNIQUE using index "permissions_name_guard_name_unique";

alter table "public"."personal_access_tokens" add constraint "personal_access_tokens_token_unique" UNIQUE using index "personal_access_tokens_token_unique";

alter table "public"."recharges" add constraint "recharges_reviewed_by_foreign" FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."recharges" validate constraint "recharges_reviewed_by_foreign";

alter table "public"."recharges" add constraint "recharges_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."recharges" validate constraint "recharges_user_id_foreign";

alter table "public"."role_has_permissions" add constraint "role_has_permissions_permission_id_foreign" FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE not valid;

alter table "public"."role_has_permissions" validate constraint "role_has_permissions_permission_id_foreign";

alter table "public"."role_has_permissions" add constraint "role_has_permissions_role_id_foreign" FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE not valid;

alter table "public"."role_has_permissions" validate constraint "role_has_permissions_role_id_foreign";

alter table "public"."roles" add constraint "roles_name_guard_name_unique" UNIQUE using index "roles_name_guard_name_unique";

alter table "public"."settings" add constraint "settings_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE CASCADE not valid;

alter table "public"."settings" validate constraint "settings_city_id_foreign";

alter table "public"."settings" add constraint "settings_city_id_key_unique" UNIQUE using index "settings_city_id_key_unique";

alter table "public"."support_conversations" add constraint "support_conversations_closed_by_foreign" FOREIGN KEY (closed_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."support_conversations" validate constraint "support_conversations_closed_by_foreign";

alter table "public"."support_conversations" add constraint "support_conversations_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."support_conversations" validate constraint "support_conversations_user_id_foreign";

alter table "public"."support_messages" add constraint "support_messages_sender_id_foreign" FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."support_messages" validate constraint "support_messages_sender_id_foreign";

alter table "public"."support_messages" add constraint "support_messages_support_conversation_id_foreign" FOREIGN KEY (support_conversation_id) REFERENCES public.support_conversations(id) ON DELETE CASCADE not valid;

alter table "public"."support_messages" validate constraint "support_messages_support_conversation_id_foreign";

alter table "public"."trip_offers" add constraint "trip_offers_driver_id_foreign" FOREIGN KEY (driver_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."trip_offers" validate constraint "trip_offers_driver_id_foreign";

alter table "public"."trip_offers" add constraint "trip_offers_trip_id_driver_id_unique" UNIQUE using index "trip_offers_trip_id_driver_id_unique";

alter table "public"."trip_offers" add constraint "trip_offers_trip_id_foreign" FOREIGN KEY (trip_id) REFERENCES public.trips(id) ON DELETE CASCADE not valid;

alter table "public"."trip_offers" validate constraint "trip_offers_trip_id_foreign";

alter table "public"."trip_ratings" add constraint "trip_ratings_driver_id_foreign" FOREIGN KEY (driver_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."trip_ratings" validate constraint "trip_ratings_driver_id_foreign";

alter table "public"."trip_ratings" add constraint "trip_ratings_passenger_id_foreign" FOREIGN KEY (passenger_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."trip_ratings" validate constraint "trip_ratings_passenger_id_foreign";

alter table "public"."trip_ratings" add constraint "trip_ratings_trip_id_foreign" FOREIGN KEY (trip_id) REFERENCES public.trips(id) ON DELETE CASCADE not valid;

alter table "public"."trip_ratings" validate constraint "trip_ratings_trip_id_foreign";

alter table "public"."trip_ratings" add constraint "trip_ratings_trip_id_unique" UNIQUE using index "trip_ratings_trip_id_unique";

alter table "public"."trips" add constraint "trips_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL not valid;

alter table "public"."trips" validate constraint "trips_city_id_foreign";

alter table "public"."trips" add constraint "trips_coupon_id_foreign" FOREIGN KEY (coupon_id) REFERENCES public.coupons(id) ON DELETE SET NULL not valid;

alter table "public"."trips" validate constraint "trips_coupon_id_foreign";

alter table "public"."trips" add constraint "trips_driver_id_foreign" FOREIGN KEY (driver_id) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."trips" validate constraint "trips_driver_id_foreign";

alter table "public"."trips" add constraint "trips_passenger_id_foreign" FOREIGN KEY (passenger_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."trips" validate constraint "trips_passenger_id_foreign";

alter table "public"."user_documents" add constraint "user_documents_reviewed_by_foreign" FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."user_documents" validate constraint "user_documents_reviewed_by_foreign";

alter table "public"."user_documents" add constraint "user_documents_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."user_documents" validate constraint "user_documents_user_id_foreign";

alter table "public"."user_documents" add constraint "user_documents_user_id_type_unique" UNIQUE using index "user_documents_user_id_type_unique";

alter table "public"."users" add constraint "users_city_id_foreign" FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL not valid;

alter table "public"."users" validate constraint "users_city_id_foreign";

alter table "public"."users" add constraint "users_email_role_unique" UNIQUE using index "users_email_role_unique";

alter table "public"."users" add constraint "users_national_id_role_unique" UNIQUE using index "users_national_id_role_unique";

alter table "public"."users" add constraint "users_reviewed_by_foreign" FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."users" validate constraint "users_reviewed_by_foreign";

alter table "public"."verification_reviews" add constraint "verification_reviews_auditor_id_foreign" FOREIGN KEY (auditor_id) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."verification_reviews" validate constraint "verification_reviews_auditor_id_foreign";

alter table "public"."verification_reviews" add constraint "verification_reviews_document_id_foreign" FOREIGN KEY (document_id) REFERENCES public.user_documents(id) ON DELETE SET NULL not valid;

alter table "public"."verification_reviews" validate constraint "verification_reviews_document_id_foreign";

alter table "public"."verification_reviews" add constraint "verification_reviews_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."verification_reviews" validate constraint "verification_reviews_user_id_foreign";

alter table "public"."wallet_transactions" add constraint "wallet_transactions_created_by_foreign" FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."wallet_transactions" validate constraint "wallet_transactions_created_by_foreign";

alter table "public"."wallet_transactions" add constraint "wallet_transactions_wallet_id_foreign" FOREIGN KEY (wallet_id) REFERENCES public.wallets(id) ON DELETE CASCADE not valid;

alter table "public"."wallet_transactions" validate constraint "wallet_transactions_wallet_id_foreign";

alter table "public"."wallets" add constraint "wallets_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."wallets" validate constraint "wallets_user_id_foreign";

alter table "public"."wallets" add constraint "wallets_user_id_unique" UNIQUE using index "wallets_user_id_unique";

alter table "public"."withdrawals" add constraint "withdrawals_reviewed_by_foreign" FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL not valid;

alter table "public"."withdrawals" validate constraint "withdrawals_reviewed_by_foreign";

alter table "public"."withdrawals" add constraint "withdrawals_user_id_foreign" FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE not valid;

alter table "public"."withdrawals" validate constraint "withdrawals_user_id_foreign";


