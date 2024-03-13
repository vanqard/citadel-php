<?php

namespace CitadelClient;

class ResolveSessionResponse {
    public ?ResolvedSession $session;
    public Recommended $recommended;
}

class ResolvedSession {
    public string $id;
    public string $sid;
    public array $identities;
    public string $audience;
    public \DateTime $issuedAt;
    public \DateTime $refreshedAt;
    public \DateTime $expiresAt;
    public \DateTime $resolvedAt;
}

class ResolvedIdentity {
    public string $id;
    public \DateTime $assignedAt;
    public string $user;
    public array $data;
}

class ResolvedValue {
    public string $name;
    public $value; // It can be string, number, boolean, or null
    public string $from;
}

class MultiValueHeaders {
    public array $headers;
}

class Recommended {
    public string $action;
    public MultiValueHeaders $responseHeaders;
    public string $reason;
}

class SessionResolveRequest {
    public string $cookieHeader;
    public string $clientId;
    public string $clientSecret;
}

class SessionResolveResponse {
    public ?ResolvedSession $session;
    public Recommended $recommended;
}

class SessionRevokeRequest {
    public string $cookieHeader;
    public string $clientId;
    public array $clientSecret;
}

class SessionRevokeResponse {
    public array $responseHeaders;
}

class SessionResolveBearerRequest {
    public string $token;
}

class SessionResolveBearerResponse {
    public ?ResolvedSession $session;
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
        $response = $this->sendRequest('/sessions.resolve', $request);
        return $response;
    }

    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse {
        $response = $this->sendRequest('/sessions.revoke', $request);
        return $response;
    }

    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse {
        $response = $this->sendRequest('/sessions.resolveBearer', $request);
        return $response;
    }

    private function sendRequest(string $action, $request) {
        $requestBody = json_encode($request);
        $response = $this->client->post($this->baseUrl . $action, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $requestBody
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode === 400) {
            $errorResponse = json_decode($body);
            throw new \Exception("API error ({$errorResponse->errorId}): {$errorResponse->error}");
        }

        return json_decode($body);
    }
}

