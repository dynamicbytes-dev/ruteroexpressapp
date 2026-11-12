-- Plantilla para crear el rol de la aplicación en un proyecto Supabase nuevo.
-- Ejecútala como `postgres` DESPUÉS de `supabase db push --include-seed`.
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
