<?php

namespace App\Service;

use App\Entity\ApiToken\ApiToken;
use App\Entity\Security\AccessToken;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Security\Token;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class AccessTokenService {

    public function __construct(
        private TokenService $tokenService,
    ) {}


    public function persistAccessToken(EntityManagerInterface $entityManager, AccessTokenTypeEnum $type, Utilisateur $owner): AccessToken {
        $now = new DateTime();
        $tokenValue = $this->tokenService->generateToken(Token::TOKEN_DEFAULT_LENGTH);

        $tokenApi = (new AccessToken($tokenValue))
            ->setType($type)
            ->setCreatedAt($now)
            ->setExpireAt((clone $now)->add(new DateInterval("PT{$type->getExpirationDelay()}S")))
            ->setOwner($owner);

        $entityManager->persist($tokenApi);

        return $tokenApi;
    }

    public function closeActiveTokens(EntityManagerInterface $entityManager, Utilisateur $user, AccessTokenTypeEnum $type): void {
        $now = new DateTime();
        $tokenRepository = $entityManager->getRepository(AccessToken::class);
        $tokens = $tokenRepository->findBy(["owner" => $user, "type" => $type]);

        foreach ($tokens as $token) {
            $token->setExpireAt($now);
        }
    }
}
