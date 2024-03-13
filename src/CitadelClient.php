<?php namespace CitadelClient;

class ResolveSessionResponse {
    public function __construct(
        public ?ResolvedSession $session,
        public Recommended $recommended
    ) {}
}

class ResolvedSession {
    public function __construct(
        public string $id,
        public string $sid,
        public array $identities,
        public string $audience,
        public \DateTime $issuedAt,
        public \DateTime $refreshedAt,
        public \DateTime $expiresAt,
        public \DateTime $resolvedAt
    ) {}
}

class ResolvedIdentity {
    public function __construct(
        public string $id,
        public \DateTime $assignedAt,
        public string $user,
        public array $data
    ) {}
}

class ResolvedValue {
    public function __construct(
        public string $name,
        public $value, // It can be string, number, boolean, or null
        public string $from
    ) {}
}

class MultiValueHeaders {
    public function __construct(
        public array $headers
    ) {}
}

class Recommended {
    public function __construct(
        public string $action,
        public MultiValueHeaders $responseHeaders,
        public string $reason
    ) {}
}

class SessionResolveRequest {
    public function __construct(
        public string $cookieHeader,
        public string $clientId,
        public string $clientSecret
    ) {}
}

class SessionResolveResponse {
    public function __construct(
        public ?ResolvedSession $session,
        public Recommended $recommended
    ) {}
}

class SessionRevokeRequest {
    public function __construct(
        public string $cookieHeader,
        public string $clientId,
        public array $clientSecret
    ) {}
}

class SessionRevokeResponse {
    public function __construct(
        public array $responseHeaders
    ) {}
}

class SessionResolveBearerRequest {
    public function __construct(
        public string $token
    ) {}
}

class SessionResolveBearerResponse {
    public function __construct(
        public ?ResolvedSession $session
    ) {}
}

interface Client {
    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse;
    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse;
    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse;
}

class HttpClient implements Client {
    private string $baseUrl;
    private \GuzzleHttp\Client $client;
    private string $preSharedKey;

    public function __construct(string $baseUrl, string $preSharedKey) {
        $this->baseUrl = $baseUrl;
        $this->client = new \GuzzleHttp\Client();
        $this->preSharedKey = $preSharedKey;
    }

    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse {
        return $this->sendRequest('/sessions.resolve', $request, function ($responseData) {
            $session = isset($responseData['session']) ? $this->mapResolvedSession($responseData['session']) : null;
            $recommended = new Recommended(
                $responseData['recommended']['action'],
                new MultiValueHeaders($responseData['recommended']['responseHeaders']),
                $responseData['recommended']['reason']
            );
            return new ResolveSessionResponse($session, $recommended);
        });
    }

    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse {
        return $this->sendRequest('/sessions.revoke', $request, function ($responseData) {
            return new SessionRevokeResponse($responseData['responseHeaders']);
        });
    }

    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse {
        return $this->sendRequest('/sessions.resolveBearer', $request, function ($responseData) {
            $session = isset($responseData['session']) ? $this->mapResolvedSession($responseData['session']) : null;
            return new SessionResolveBearerResponse($session);
        });
    }

    private function sendRequest(string $action, $request, callable $mapper) {
        $requestBody = json_encode($request);
        $response = $this->client->post($this->baseUrl . $action, [
            'headers' => ['Content-Type' => 'application/json', 'x-sdk-version' => '0.2.0-php'],
            'body' => $requestBody
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode === 400) {
            $errorResponse = json_decode($body);
            throw new \Exception("API error ({$errorResponse->errorId}): {$errorResponse->error}");
        }

        $responseData = json_decode($body, true);

        return $mapper($responseData);
    }

    private function mapResolvedSession(array $sessionData): ResolvedSession {
        return new ResolvedSession(
            $sessionData['id'],
            $sessionData['sid'],
            array_map(function ($identity) {
                return new ResolvedIdentity(
                    $identity['id'],
                    new \DateTime($identity['assignedAt']),
                    $identity['user'],
                    array_map(function ($value) {
                        return new ResolvedValue(
                            $value['name'],
                            $value['value'],
                            $value['from']
                        );
                    }, $identity['data'])
                );
            }, $sessionData['identities']),
            $sessionData['audience'],
            new \DateTime($sessionData['issuedAt']),
            new \DateTime($sessionData['refreshedAt']),
            new \DateTime($sessionData['expiresAt']),
            new \DateTime($sessionData['resolvedAt'])
        );
    }
}