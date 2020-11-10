<?php

/**
 *
 * RequestDirect is always started directly from code and coder knows why it is started.
 *
 * @author dso
 *
 */
class RequestDirect extends Request
{

    /**
     * Get request object from stack trace
     *
     * @return RequestDirect
     */
    public static function create(string $tenantId, string $implementationId, array $payload = [])
    {
        $req = new RequestDirect();

        $user = Session::getUser();
        if (! ($user instanceof User)) {
            throw new GenException('User session must be set for direct request handling');
        }
        $req->payloadArr = $payload;
        $req->payload = json_encode($payload);
        $req->implementationId = $implementationId;
        $req->setContentType('JSON');

        $req->setTenantId($tenantId);
        $req->setUserId($user->getId());
        $req->time = su::timeToBSON();
        $req->getAndSetImplementation();

        $req->saveToDb();
        Log::setRequestId($req->getId());

        return $req;
    }
}