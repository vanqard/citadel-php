<?php
namespace CitadelClient;

use DateTime;
use Exception;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use PsrDiscovery\Discover;
use PsrDiscovery\Exceptions\SupportPackageNotFoundException;

class HttpClient implements CitadelClientInterface
{
    public const PHP_SDK_VERSION = '0.3.2-php';

    public const PHP_SDK_SIGNATURE_HEADER = 'x-citadel-sig';

    private PsrClientInterface $client;

    private RequestFactoryInterface $messageFactory;

    private StreamFactoryInterface $streamFactory;

    private string $baseUrl;

    private string $preSharedKey;

    public function __construct(string $baseUrl, string $preSharedKey, ?PsrClientInterface $client = null)
    {
        $this->baseUrl = $baseUrl;
        $this->preSharedKey = $preSharedKey;
        $this->setup($client);
    }

    private function setup(?PsrClientInterface $client = null): void
    {
        if ($client instanceof PsrClientInterface) {
            $this->client = $client;
        } else {
            try {
                $this->client = Discover::httpClient();
            } catch (SupportPackageNotFoundException $e) {
                throw new CitadelClientException("Please install a PSR-18 compliant http client");
            }
        }

        $messageFactory = Discover::httpRequestFactory();

        if (!$messageFactory instanceof RequestFactoryInterface) {
            throw new CitadelClientException("Please install a PSR-17 compliant http factory");
        }

        $this->messageFactory = $messageFactory;

        $streamFactory = Discover::httpStreamFactory();

        if (!$streamFactory instanceof StreamFactoryInterface) {
            throw new CitadelClientException("Please install a PSR-17 compliant stream factory");
        }

        $this->streamFactory = $streamFactory;
    }

    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse
    {
        return $this->sendRequest('/sessions.resolve', $request, function (
            $responseData
        ): ResolveSessionResponse {
            $session = isset($responseData['session']) ? $this->mapResolvedSession($responseData['session']) : null;
            $recommended = new Recommended(
                $responseData['recommended']['action'],
                new MultiValueHeaders($responseData['recommended']['responseHeaders']),
                $responseData['recommended']['reason']
            );
            return new ResolveSessionResponse($recommended, $session);
        });
    }

    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse
    {
        return $this->sendRequest('/sessions.revoke', $request, function (
            $responseData
        ): SessionRevokeResponse {
            return new SessionRevokeResponse($responseData['responseHeaders']);
        });
    }

    /**
     * @throws CitadelClientException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse
    {
        return $this->sendRequest('/sessions.resolveBearer', $request, function (
            $responseData
        ): SessionResolveBearerResponse {
            $session = isset($responseData['session']) ? $this->mapResolvedSession($responseData['session']) : null;
            return new SessionResolveBearerResponse($session);
        });
    }

    /**
     * @throws CitadelClientException
     * @throws ClientExceptionInterface
     */
    private function sendRequest(
        string $action,
        SessionResolveRequest | SessionRevokeRequest | SessionResolveBearerRequest $request,
        callable $mapper
    ): mixed {
        try {
            $requestBody = json_encode($request, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CitadelClientException("The request data could not be json encoded", 0, $e);
        }

        $requestInstance = $this->createRequest($action, $requestBody);
        $response = $this->client->sendRequest($requestInstance);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if (400 === $statusCode) {
            $errorResponse = json_decode($body, false);
            throw new CitadelClientException("API error ({$errorResponse->errorId}): {$errorResponse->error}");
        }

        $responseData = json_decode($body, true);

        return $mapper($responseData);
    }

    private function createRequest(string $action, string $requestBody): RequestInterface
    {
        $requestInstance = $this->messageFactory->createRequest(
            'POST',
            sprintf("%s%s", $this->baseUrl, $action)
        );

        $stream = $this->streamFactory->createStream($requestBody);

        return $requestInstance
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('x-sdk-version', self::PHP_SDK_VERSION)
            ->withHeader(self::PHP_SDK_SIGNATURE_HEADER, $this->generateRequestBodySignature($requestBody))
            ->withBody($stream);
    }

    private function generateRequestBodySignature(string $jsonRequestBody): string
    {
        return hash_hmac('sha256', $jsonRequestBody, $this->preSharedKey);
    }

    /**
     * @throws CitadelClientException
     */
    private function mapResolvedSession(array $sessionData): ResolvedSession
    {
        try {
            $issuedAt = new DateTime($sessionData['issuedAt']);
            $refreshedAt = new DateTime($sessionData['refreshedAt']);
            $expiresAt = new DateTime($sessionData['expiresAt']);
            $resolvedAt = new DateTime($sessionData['resolvedAt']);
        } catch (Exception) {
            throw new CitadelClientException(
                "Invalid date value received in session response"
            );
        }

        return new ResolvedSession(
            $sessionData['id'],
            $sessionData['sid'],
            array_map(static function ($identity) {

                try {
                    $assignedAt = new DateTime($identity['assignedAt']);
                } catch (Exception) {
                    throw new CitadelClientException(
                        "Invalid date value received for the assignedAt property"
                    );
                }

                return new ResolvedIdentity(
                    $identity['id'],
                    $assignedAt,
                    $identity['user'],
                    array_map(static function ($value) {
                        return new ResolvedValue(
                            $value['name'],
                            $value['value'],
                            $value['from']
                        );
                    }, $identity['data'])
                );
            }, $sessionData['identities']),
            $sessionData['audience'],
            $issuedAt,
            $refreshedAt,
            $expiresAt,
            $resolvedAt
        );
    }
}
