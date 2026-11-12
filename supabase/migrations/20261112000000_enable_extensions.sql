-- Extensiones que usa el esquema de GAZ.
-- Supabase las instala en el esquema `extensions`; Laravel las resuelve con
-- `DB_SEARCH_PATH=public,extensions`.
create extension if not exists postgis with schema extensions;
create extension if not exists unaccent with schema extensions;
create extension if not exists "uuid-ossp" with schema extensions;
