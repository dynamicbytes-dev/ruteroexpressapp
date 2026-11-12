-- Hardening del esquema público de GAZ.
--
-- Laravel es el único cliente de la base: no usamos Supabase Auth, Storage ni
-- el Data API. Por eso `anon`, `authenticated` y `service_role` no deben tener
-- acceso a las tablas de la aplicación.
--
-- Esta migración es idempotente y también se ejecuta desde
-- `supabase/roles.example.sql` al preparar un proyecto nuevo.
--
-- Importante: `gaz_app` es dueño de las tablas y `postgres` tiene BYPASSRLS,
-- así que activar RLS sin políticas no afecta a Laravel ni al panel.

-- Objetos que ya existen en `public`.
revoke all on all tables in schema public from anon, authenticated, service_role;
revoke all on all sequences in schema public from anon, authenticated, service_role;
revoke all on all functions in schema public from anon, authenticated, service_role;
revoke all on all functions in schema public from public;

-- Objetos que se creen en el futuro desde `postgres` (SQL editor, db push, etc.).
alter default privileges for role postgres in schema public
    revoke all on tables from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on sequences from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on functions from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on functions from public;

-- `supabase_admin` conserva sus propios default privileges porque `postgres`
-- no es miembro de ese rol. Sus objetos viven en los esquemas internos de
-- Supabase; si alguna vez se crean tablas en `public` con ese rol, hay que
-- repetir estos REVOKE como `supabase_admin`.
do $$
begin
    execute 'alter default privileges for role supabase_admin in schema public '
        || 'revoke all on tables from anon, authenticated, service_role';
    execute 'alter default privileges for role supabase_admin in schema public '
        || 'revoke all on sequences from anon, authenticated, service_role';
    execute 'alter default privileges for role supabase_admin in schema public '
        || 'revoke all on functions from anon, authenticated, service_role';
    execute 'alter default privileges for role supabase_admin in schema public '
        || 'revoke all on functions from public';
exception
    when insufficient_privilege then
        raise notice 'No se pudieron cambiar los default privileges de supabase_admin desde postgres; revisar si ese rol crea objetos en public.';
end
$$;

-- Defensa en profundidad: aunque alguien vuelva a conceder privilegios a
-- `anon`, `authenticated` o `service_role`, RLS sin políticas no devuelve filas.
do $$
declare
    tabla record;
begin
    for tabla in
        select c.relname
        from pg_class c
        join pg_namespace n on n.oid = c.relnamespace
        where n.nspname = 'public'
          and c.relkind = 'r'
    loop
        execute format('alter table public.%I enable row level security', tabla.relname);
    end loop;
end
$$;
