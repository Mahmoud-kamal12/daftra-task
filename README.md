# Simplified Inventory Management API

A robust and scalable REST API for managing warehouse inventory and stock transfers, built with Laravel.

## Project Overview

This API allows for:
- Managing multiple warehouses.
- Tracking inventory items and their stock levels across warehouses.
- Performing stock transfers between warehouses with consistency checks.
- Real-time low stock notifications.
- Efficient searching and filtering of inventory.

## Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/Mahmoud-kamal12/daftra
   cd daftra
   ```
2. **run docker-compose**   
   ```bash
   docker-compose up -d
   ```
3. **Run Seeder**
   ```bash
   docker-compose exec daftra php artisan db:seed
   ```
## Testing

Run the feature tests to verify functionality:

```bash
php artisan test
```

Includes tests for:
- Successful stock transfers.
- Insufficient stock validation.
- Inventory listing and filtering.
- Cache interaction.
