-- Base de datos aparte para las pruebas automatizadas.
-- Se crea junto al volumen, así los tests nunca tocan los datos de desarrollo.
CREATE DATABASE motoruta_testing OWNER motoruta;

\connect motoruta_testing

CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
