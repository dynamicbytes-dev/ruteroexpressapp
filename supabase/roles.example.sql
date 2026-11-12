-- Plantilla para crear el rol de la aplicación en un proyecto Supabase nuevo.
-- Ejecútala como `postgres` DESPUÉS de `supabase db push --remote --include-seed`.
-- Reemplaza <PASSWORD>; la contraseña real debe quedar en backend/.env.
create role gaz_app login password '<PASSWORD>';

grant connect on database postgres to gaz_app;
grant usage on schema public, extensions to gaz_app;
grant create on schema public to gaz_app;
alter role gaz_app in database postgres set search_path = public, extensions;

-- Permisos de lectura/escritura para la aplicación.
grant select, insert, update, delete on all tables in schema public to gaz_app;
grant usage, select on all sequences in schema public to gaz_app;
grant execute on all functions in schema public to gaz_app;

-- Permisos para objetos que se creen en el futuro desde `postgres`.
alter default privileges in schema public grant select, insert, update, delete on tables to gaz_app;
alter default privileges in schema public grant usage, select on sequences to gaz_app;
alter default privileges in schema public grant execute on functions to gaz_app;

-- El rol de la app debe ser dueño de las tablas para poder ejecutar
-- `php artisan migrate` en el futuro. `postgres` puede transferirlas porque
-- creó el rol.
do $$
declare
    r record;
begin
    for r in select tablename from pg_tables where schemaname = 'public' loop
        execute format('alter table public.%I owner to gaz_app', r.tablename);
    end loop;
    for r in select sequencename from pg_sequences where schemaname = 'public' loop
        execute format('alter sequence public.%I owner to gaz_app', r.sequencename);
    end loop;
end
$$;

-- Hardening: Laravel es el único cliente. El Data API de Supabase no se usa,
-- así que `anon`, `authenticated` y `service_role` no deben leer ni escribir
-- las tablas de GAZ. La migración 20261112220000 hace lo mismo para proyectos
-- que aplican las migraciones con `supabase db push`.
revoke all on all tables in schema public from anon, authenticated, service_role;
revoke all on all sequences in schema public from anon, authenticated, service_role;
revoke all on all functions in schema public from anon, authenticated, service_role;
revoke all on all functions in schema public from public;

alter default privileges for role postgres in schema public
    revoke all on tables from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on sequences from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on functions from anon, authenticated, service_role;
alter default privileges for role postgres in schema public
    revoke all on functions from public;

-- Defensa en profundidad: RLS sin políticas no devuelve filas a los roles
-- públicos. `gaz_app` es owner y `postgres` tiene BYPASSRLS, así que Laravel y
-- el panel siguen funcionando.
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
