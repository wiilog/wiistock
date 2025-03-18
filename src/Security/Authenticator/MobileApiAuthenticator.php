<?php

namespace App\Security\Authenticator;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class MobileApiAuthenticator extends AbstractAuthenticator {

    private const AUTHENTICATION_HEADER = "x-authorization";

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}


    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool {
        return $request->headers->has(self::AUTHENTICATION_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get(self::AUTHENTICATION_HEADER);

        $userRepository = $this->entityManager->getRepository(Utilisateur::class);
        preg_match("/Bearer (\w*)/i", $authorization ?: "", $matches);
        $apiToken = $matches[1] ?? null;

        if (!$apiToken) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        $userIdentifier = $userRepository->findOneByApiKey($apiToken);

        if (!$userIdentifier) {
            throw new CustomUserMessageAuthenticationException('Invalid API token');
        }

        return new SelfValidatingPassport(new UserBadge($userIdentifier->getEmail()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request                 $request,
                                            AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            "message" => strtr($exception->getMessageKey(), $exception->getMessageData()),
            "success" => false,
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
