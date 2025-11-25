## FBA shipment demo service

### Prerequisites
- PHP 8.2 or higher
- composer

1. Install dependencies
```bash
composer install
```

2. Run tests
```bash
./vendor/bin/phpunit tests
```

3. Configure environment
```bash
cp .env.example .env
```

Open `.env` and set your `AMAZON_ACCESS_TOKEN`.

4. Run the app
```bash
php -S localhost:8000 -t public
```

Now you can access the app with your http client (Postman, etc.). A full OpenAPI documentation is available in `swagger.yaml` file.
