# Docker Setup for Invoxo

This document describes the Docker setup for running Invoxo with Laravel Octane (Swoole) and PostgreSQL.

## Architecture

- **app**: Laravel Octane web server (Swoole)
- **worker**: Queue worker for background jobs
- **scheduler**: Laravel task scheduler
- **postgres**: PostgreSQL 16 database

All Laravel code containers bind-mount the repository into `/app` for development.

## Prerequisites

- Docker and Docker Compose installed
- Git repository cloned

## Quick Start

1. **Start services:**
   ```bash
   docker compose up -d --build
   ```

2. **Run migrations:**
   ```bash
   docker compose exec app php artisan migrate
   ```

3. **Access the application:**
   - Web: http://localhost:8000
   - Database: localhost:5432
     - Database: `invoxo`
     - User: `invoxo`
     - Password: `invoxo_password`

## Verification

### Verify Swoole is installed:
```bash
docker compose exec app php -m | grep -i swoole
```
Should output: `swoole`

### Run tests:
```bash
docker compose exec app php artisan test
```

### Check logs:
```bash
docker compose logs app
docker compose logs worker
docker compose logs scheduler
```

## Common Commands

### Run Artisan commands:
```bash
docker compose exec app php artisan <command>
```

### Access container shell:
```bash
docker compose exec app sh
```

### View database:
```bash
docker compose exec postgres psql -U invoxo -d invoxo
```

### Restart services:
```bash
docker compose restart app worker scheduler
```

### Stop all services:
```bash
docker compose down
```

### Stop and remove volumes (⚠️ deletes database data):
```bash
docker compose down -v
```

## Development Notes

### Bind Mounts

All Laravel code containers (`app`, `worker`, `scheduler`) bind-mount `./` into `/app` for live code updates during development.

### Permissions

In development, containers run as root to simplify file permissions. The host user's files are accessible directly through the bind mount.

### Environment Variables

Default environment variables are set in `docker-compose.yml`. To override:
1. Create a `.env` file (it will be bind-mounted)
2. Or use `docker compose run` with `-e` flags
3. Or modify `docker-compose.yml` environment section

## Production Deployment

**⚠️ Important: The following steps are required for production:**

1. **Remove bind mounts:**
   - Remove `volumes: - ./:/app` from all services
   - Ensure code is copied during build (already in Dockerfile)

2. **Do NOT run migrations/key:generate on container start:**
   - Remove `php artisan key:generate` and `php artisan migrate` from `command`
   - Run these in deployment scripts instead

3. **Set production environment:**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - Ensure `APP_KEY` is set and persisted (not regenerated)

4. **Use secrets management:**
   - Store database credentials and other secrets securely
   - Consider using Docker secrets or environment variable management

5. **Optimize Composer:**
   - Use `composer install --no-dev --optimize-autoloader` in production builds

6. **Configure Swoole:**
   - Review Octane configuration for production settings
   - Consider setting worker count and other performance parameters

7. **Health checks:**
   - Add health checks to `app` service if needed
   - Monitor container health in production

## Troubleshooting

### Port already in use:
```bash
# Change port in docker-compose.yml
ports:
  - "8001:8000"  # Use different host port
```

### Permission errors:
- Ensure storage directories are writable on host if bind-mounting
- Or let containers create them with appropriate permissions

### Database connection issues:
- Verify postgres container is healthy: `docker compose ps`
- Check environment variables match postgres service
- Ensure postgres healthcheck passes before app starts

### Swoole not found:
- Verify build output shows `swoole` installation
- Check Dockerfile includes Swoole installation steps
- Rebuild image: `docker compose build --no-cache`






