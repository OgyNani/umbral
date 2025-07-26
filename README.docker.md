# Docker Setup for Umbral Project

This document explains how to run the Umbral project using Docker.

## Prerequisites

- Docker
- Docker Compose

## Getting Started

1. Build and start the containers:

```bash
docker-compose up -d --build
```

2. The application will be available at: http://localhost:8080

## Services

- **PHP**: PHP 8.2 with FPM
- **Nginx**: Web server running on port 8080
- **PostgreSQL**: Database running on port 5432
  - Database: webgame
  - Username: postgres
  - Password: postgres

## Database Migrations

To run database migrations:

```bash
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

## Accessing the Database

You can connect to the PostgreSQL database using:

```bash
docker-compose exec database psql -U postgres -d webgame
```

## Stopping the Containers

```bash
docker-compose down
```

To remove volumes as well:

```bash
docker-compose down -v
```
