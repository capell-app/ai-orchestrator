# Exchanger Documentation

Exchanger is responsible for synchronizing content across services.

## Configuration

Use `exchanger.php` to configure Exchanger behavior.

## Usage

```php
use App\Services\Exchanger;

$sync = new Exchanger();
$sync->sync();
```

## Environment Variables

- `EXCHANGER_TIMEOUT`: Timeout in seconds (default: 300)
- `EXCHANGER_ENABLED`: Enable/disable Exchanger (default: true)

## Implementation

Exchanger implements ExchangerInterface for consistency.
