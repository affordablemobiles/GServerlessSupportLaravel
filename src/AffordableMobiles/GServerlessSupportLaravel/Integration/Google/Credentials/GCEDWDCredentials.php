<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Integration\Google\Credentials;

use AffordableMobiles\GServerlessSupportLaravel\Integration\Google\OAuth2;
use Google\Auth\Credentials\GCECredentials;
use Google\Auth\Iam;

/**
 * GCEDWDCredentials supports Domain Wide Delegation authorization on Google Compute Engine.
 *
 *  use Google\Client;
 *  use Google\Service\Directory;
 *  use AffordableMobiles\GServerlessSupportLaravel\Integration\Google\Credentials\GCEDWDCredentials;
 *
 *  $client = new Client([
 *      'credentials' => (new GCEDWDCredentials(
 *          scope: Directory::ADMIN_DIRECTORY_GROUP_MEMBER_READONLY,
 *      ))->setSubject(env('GSUITE_ADMIN_IMPERSONATE')),
 *  ]);
 *
 *  $directory = new Directory($client);
 *
 *  $isMember = $directory->members->hasMember($group, $user)->getIsMember();
 */
class GCEDWDCredentials extends GCECredentials
{
    /**
     * User to impersonate for Domain Wide Delegation.
     *
     * @var string
     */
    private $subject = false;

    /**
     * Scopes to request in token.
     *
     * @var string
     */
    private $scope = false;

    /**
     * @param Iam             $iam                    [optional] An IAM instance
     * @param string|string[] $scope                  [optional] the scope of the access request,
     *                                                expressed either as an array or as a space-delimited string
     * @param string          $targetAudience         [optional] The audience for the ID token
     * @param string          $quotaProject           [optional] Specifies a project to bill for access
     *                                                charges associated with the request
     * @param string          $serviceAccountIdentity [optional] Specify a service
     *                                                account identity name to use instead of "default"
     */
    public function __construct(
        ?Iam $iam = null,
        $scope = null,
        $targetAudience = null,
        $quotaProject = null,
        $serviceAccountIdentity = null
    ) {
        $this->scope = $scope;

        parent::__construct();
    }

    /**
     * Set the Domain Wide Delegation impersonation subject.
     *
     * @param string $subject email address of the user to impersonate
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        $this->auth = new OAuth2([
            'audience'           => self::TOKEN_CREDENTIAL_URI,
            'issuer'             => $this->getClientName(),
            'scope'              => $this->scope,
            'signingAlgorithm'   => 'RS256',
            'signingKey'         => [$this, 'signOAuth2'],
            'sub'                => $this->subject,
            'tokenCredentialUri' => self::TOKEN_CREDENTIAL_URI,
            'additionalClaims'   => [],
        ]);

        return $this;
    }

    /**
     * Signer callback to OAuth2->JWT, to use signBlob from GCE/IAM.
     *
     * @param string $alg signing algorithm requested
     * @param string $msg message to sign
     *
     * @throws \DomainException
     */
    public function signOAuth2(string $alg, string $msg): string
    {
        if ('RS256' !== $alg) {
            throw new \DomainException('Algorithm not supported');
        }

        $previousToken = $this->getLastReceivedToken();

        $subject       = $this->subject;
        $this->subject = null;

        $accessToken = $previousToken ? $previousToken['access_token'] : $this->fetchAuthToken()['access_token'];

        $this->subject = $subject;

        return base64_decode($this->signBlob($msg, false, $accessToken), true);
    }

    /**
     * Implements FetchAuthTokenInterface#fetchAuthToken.
     *
     * Fetches the auth tokens from the GCE metadata host if it is available.
     * If $httpHandler is not specified a the default HttpHandler is used.
     *
     * @param callable $httpHandler callback which delivers psr7 request
     * @param array<mixed> $headers [optional] Metrics headers to be inserted into the token endpoint request present.
     *
     * @return array<mixed> {
     *                      A set of auth related metadata, based on the token type
     *
     * @var string $access_token for access tokens
     * @var int    $expires_in   for access tokens
     * @var string $token_type   for access tokens
     * @var string $id_token     for ID tokens
     *             }
     *
     * @throws \Exception
     */
    public function fetchAuthToken(?callable $httpHandler = null, array $headers = [])
    {
        if (empty($this->subject)) {
            return parent::fetchAuthToken($httpHandler, $headers);
        }

        return $this->auth->fetchAuthToken($httpHandler, $headers);
    }
}
