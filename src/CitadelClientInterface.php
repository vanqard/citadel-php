<?php
namespace CitadelClient;

interface CitadelClientInterface
{
    public function sessionResolve(SessionResolveRequest $request): ResolveSessionResponse;
    public function sessionRevoke(SessionRevokeRequest $request): SessionRevokeResponse;
    public function sessionResolveBearer(SessionResolveBearerRequest $request): SessionResolveBearerResponse;
}
