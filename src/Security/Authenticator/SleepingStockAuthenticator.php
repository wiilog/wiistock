<?php

namespace App\Security\Authenticator;

use App\Entity\Security\AccessToken;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SleepingStockAuthenticator extends AbstractAuthenticator {

    public const ACCESS_TOKEN_PARAMETER = "access-token";

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool {
        return $request->query->has(self::ACCESS_TOKEN_PARAMETER);
    }

    public function authenticate(Request $request): Passport
    {
        $accessTokenRepository = $this->entityManager->getRepository(AccessToken::class);

        $tokenValue = $request->query->get(self::ACCESS_TOKEN_PARAMETER);

        if (!$tokenValue) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new UnauthorizedHttpException('Invalid access token');
        }

        $accessToken = $accessTokenRepository->findOneBy([
            "token" => $tokenValue,
        ]);

        $userIdentifier = $accessToken?->getOwner()?->getEmail();
        $now = new DateTime();

        // if token as empty expiresAt then it's valid
        if (!$userIdentifier
            || $accessToken->getExpireAt() && $accessToken->getExpireAt() < $now) {
            throw new UnauthorizedHttpException('Invalid access token');
        }

        return new SelfValidatingPassport(new UserBadge($userIdentifier));
    }

    public function onAuthenticationSuccess(Request        $request,
                                            TokenInterface $token,
                                            string         $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request                 $request,
                                            AuthenticationException $exception): ?Response {
        return null;
    }
}
