# Local Development & Testing Guide

This repository includes a Docker Compose environment for testing the Moyasar plugin on a fresh Magento 2 store.

## 🚀 Quick Setup

Run the setup script:

```bash
./bin/setup
```

This will automatically:
1. Start the Docker containers (Nginx, PHP 8.3, MariaDB, OpenSearch, Redis).
2. Install Magento 2 and link the Moyasar plugin.
3. Create a test sample product (`moyasar-test-item`).

## 🔑 Access Details

- **Storefront**: [https://localhost:8443/](https://localhost:8443/)
- **Admin Panel**: [https://localhost:8443/admin/](https://localhost:8443/admin/)
  - **Username**: `admin`
  - **Password**: `Admin123!`

## 🛠️ Helper Commands

```bash
# Run Magento CLI commands
./bin/m <command>          # Example: ./bin/m cache:clean

# Create sample product
./bin/create-sample-product

# Stop Docker environment
docker compose down
```

## 📦 Releases

Composer release zips automatically exclude dev files (`docker-compose.yml`, `bin/`, `DEVELOPMENT.md`) using `.gitattributes`.
