# Getting Started with SPP

To begin using the framework, make sure you have:

1. PHP 8.2+ installed
2. Apache Web Server
3. Database (MariaDB/MySQL)

```php
// An example of SPP controller routing
#[Route('/hello', method: 'GET')]
public function hello() {
    return $this->render('hello', ['name' => 'World']);
}
```

Explore the [Architecture](/sppdocs/docs/architecture) to learn more.
