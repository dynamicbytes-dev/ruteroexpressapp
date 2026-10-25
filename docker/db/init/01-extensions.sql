-- Extensiones requeridas por Motoruta.
-- Se ejecuta automáticamente la primera vez que se crea el volumen de datos.

-- Geoespacial: búsqueda de conductores cercanos (ST_DWithin, ST_Distance).
CREATE EXTENSION IF NOT EXISTS postgis;

-- Búsqueda difusa de direcciones / nombres (opcional, útil en el panel).
CREATE EXTENSION IF NOT EXISTS unaccent;

-- Identificadores únicos cortos para referencias de viaje.
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
