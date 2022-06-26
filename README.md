# Releasing tool

## Need
- Traefik

## Init
- copy .env.template to .env
- edit .env as you want
- copy src/config/local.sample.neon to src/config/local.neon
- edit src/config/local.neon as you want
- docker-compose up -d
- wait for database, login and init database with src/sql/init.sql
