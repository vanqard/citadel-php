<?php

namespace CitadelClient;

class ResolveSessionResponse
{
    public ?ResolvedSession $session;
    public Recommended $recommended;

    public function __construct(
        ?ResolvedSession $session,
        Recommended $recommended
    ) {
        $this->session = $session;
        $this->recommended = $recommended;
    }
}

class ResolvedSession
{
    public string $id;
    public string $sid;
    public array $identities;
    public string $audience;
    public \DateTime $issuedAt;
    public \DateTime $refreshedAt;
    public \DateTime $expiresAt;
    public \DateTime $resolvedAt;

    public function __construct(
        string $id,
        string $sid,
        array $identities,
        string $audience,
        \DateTime $issuedAt,
        \DateTime $refreshedAt,
        \DateTime $expiresAt,
        \DateTime $resolvedAt
    ) {
        $this->id = $id;
        $this->sid = $sid;
        $this->identities = $identities;
        $this->audience = $audience;
        $this->issuedAt = $issuedAt;
        $this->refreshedAt = $refreshedAt;
        $this->expiresAt = $expiresAt;
        $this->resolvedAt = $resolvedAt;
    }
}

class ResolvedIdentity
{
    public string $id;
    public \DateTime $assignedAt;
    public string $user;
    public array $data;
    public string $status;

    public function __construct(
        string $id,
        \DateTime $assignedAt,
        string $user,
        array $data,
        string $status
    ) {
        $this->id = $id;
        $this->assignedAt = $assignedAt;
        $this->user = $user;
        $this->data = $data;
        $this->status = $status;
    }
}

class ResolvedValue
{
    public string $name;
    /**
     * Intended to be one of string | int | bool | null
     * @var mixed
     */
    public $value;
    public string $from;

    public function __construct(
        string $name,
        $value,
        string $from
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->from = $from;

    }
}

class MultiValueHeaders
{
    public array $headers;

    public function __construct(
        array $headers
    ) {
        $this->headers = $headers;
    }
}

class Recommended
{
    public string $action;
    public MultiValueHeaders $responseHeaders;
    public string $reason;

    public function __construct(
        string $action,
        MultiValueHeaders $responseHeaders,
        string $reason
    ) {
        $this->action = $action;
        $this->responseHeaders = $responseHeaders;
        $this->reason = $reason;
    }
}

class SessionResolveRequest
{
    public string $cookieHeader;
    public string $clientId;
    public string $clientSecret;

    public function __construct(
        string $cookieHeader,
        string $clientId,
        string $clientSecret
    ) {
        $this->cookieHeader = $cookieHeader;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
}

class SessionResolveResponse
{
    public ?ResolvedSession $session;
    public Recommended $recommended;

    public function __construct(
        ?ResolvedSession $session,
        Recommended $recommended
    ) {
        $this->session = $session;
        $this->recommended = $recommended;
    }
}

class SessionRevokeRequest
{
    public string $cookieHeader;
    public string $clientId;
    public string $clientSecret;

    public function __construct(
        string $cookieHeader,
        string $clientId,
        string $clientSecret
    ) {
        $this->cookieHeader = $cookieHeader;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
}

class SessionRevokeResponse
{
    public array $responseHeaders;

    public function __construct(
        array $responseHeaders
    ) {
        $this->responseHeaders = $responseHeaders;
    }
}

class SessionResolveBearerRequest
{
    public string $token;

    public function __construct(
        string $token
    ) {
        $this->token = $token;
    }
}

class SessionResolveBearerResponse
{
    public ?ResolvedSession $session;

    public function __construct(
        ?ResolvedSession $session
    ) {
        $this->session = $session;
    }
}

interface Client
{
    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse;
    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse;
    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse;
}

class HttpClient implements Client
{
    private string $baseUrl;
    private \GuzzleHttp\Client $client;
    private string $preSharedKey;

    public function __construct(string $baseUrl, string $preSharedKey)
    {
        $this->baseUrl = $baseUrl;
        $this->client = new \GuzzleHttp\Client();
        $this->preSharedKey = $preSharedKey;
    }

    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse
    {
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

    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse
    {
        return $this->sendRequest('/sessions.revoke', $request, function ($responseData) {
            return new SessionRevokeResponse($responseData['responseHeaders']);
        });
    }

    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse
    {
        return $this->sendRequest('/sessions.resolveBearer', $request, function ($responseData) {
            $session = isset($responseData['session']) ? $this->mapResolvedSession($responseData['session']) : null;
            return new SessionResolveBearerResponse($session);
        });
    }

    private function sendRequest(string $action, $request, callable $mapper)
    {
        $requestBody = json_encode($request);
        $response = $this->client->post($this->baseUrl . $action, [
            'headers' => ['Content-Type' => 'application/json', 'x-sdk-version' => '0.3.0-php'],
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

    private function mapResolvedSession(array $sessionData): ResolvedSession
    {
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
                    }, $identity['data']),
                    $identity['status']
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
