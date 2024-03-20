# CitadelClient

CitadelClient is a PHP library for interacting with the Citadel API.

## Installation

You can install this library via Composer:

```bash
composer require everlutionsk/citadel-client
```

## Usage

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use CitadelClient\HttpClient;
use CitadelClient\SessionResolveRequest;

// Initialize the HTTP client
$client = new HttpClient('https://api.citadel.example', 'your-pre-shared-key');

// Prepare the session resolve request
$request = new SessionResolveRequest('your-cookie-header', 'your-client-id', 'your-client-secret');

try {
    // Resolve the session
    $response = $client->sessionResolve($request);

    // Handle the response
    if ($response->session) {
        echo "Session resolved successfully:\n";
        echo "Session ID: " . $response->session->id . "\n";
        // Other session details...
    } else {
        echo "No session resolved.\n";
        echo "Recommended action: " . $response->recommended->action . "\n";
        // Other recommendations...
    }
} catch (\Exception $e) {
    // Handle errors
    echo "Error: " . $e->getMessage() . "\n";
}
```

Replace 'https://api.citadel.example', 'your-pre-shared-key', 'your-cookie-header', 'your-client-id', and 'your-client-secret' with your actual Citadel API endpoint, pre-shared key, cookie header, client ID, and client secret respectively.
