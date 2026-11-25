<?php
declare(strict_types=1);

namespace App\Authenticator;

use App\Service\WebInstaller;
use ArrayAccess;
use Authentication\Authenticator\PrimaryKeySessionAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RevocableSessionAuthenticator extends PrimaryKeySessionAuthenticator
{
    /**
     * Reject sessions created before an account's credentials were changed.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @return \Authentication\Authenticator\ResultInterface
     */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $params = $request->getAttribute('params');
        if (
            ($params['controller'] ?? null) === 'Accounts' && ($params['action'] ?? null) === 'setup' &&
            (new WebInstaller(CONFIG, WWW_ROOT))->enabled()
        ) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }
        $result = parent::authenticate($request);
        $identity = $result->getData();
        $session = $request->getAttribute('session');
        if ($identity && (int)$session->read('AuthVersion') !== (int)$identity['auth_version']) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }

        return $result;
    }

    /**
     * Store the account's current session version.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Message\ResponseInterface $response Response.
     * @param mixed $identity Identity.
     * @return array
     */
    public function persistIdentity(
        ServerRequestInterface $request,
        ResponseInterface $response,
        mixed $identity,
    ): array {
        if (!is_array($identity) && !$identity instanceof ArrayAccess) {
            throw new InvalidArgumentException('The session identity must be an array or ArrayAccess.');
        }
        $result = parent::persistIdentity($request, $response, $identity);
        $request->getAttribute('session')->write('AuthVersion', (int)$identity['auth_version']);

        return $result;
    }

    /**
     * Clear the stored session version on logout.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Message\ResponseInterface $response Response.
     * @return array
     */
    public function clearIdentity(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $request->getAttribute('session')->delete('AuthVersion');

        return parent::clearIdentity($request, $response);
    }
}
