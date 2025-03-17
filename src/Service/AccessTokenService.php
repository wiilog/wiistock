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


    /**
     * @return string the value of the access token generated (before the hash),
     */
    public function persistAccessToken(EntityManagerInterface $entityManager, AccessTokenTypeEnum $type, Utilisateur $owner): string {
        $now = new DateTime();
        $newToken = $this->tokenService->generateToken(Token::TOKEN_DEFAULT_LENGTH);
        $newTokenHash = hash('sha256', $newToken);

        $this->closeActiveTokens($entityManager, $now, $owner, $type);

        $tokenApi = (new AccessToken())
            ->setToken($newTokenHash)
            ->setType($type)
            ->setCreatedAt($now)
            ->setExpireAt((clone $now)->add(new DateInterval("PT{$type->getExpirationDelay()}S")))
            ->setOwner($owner);

        $entityManager->persist($tokenApi);

        return $newToken;
    }

    private function closeActiveTokens(EntityManagerInterface $entityManager, DateTime $now, Utilisateur $user, AccessTokenTypeEnum $type): void {
        $tokenRepository = $entityManager->getRepository(AccessToken::class);
        $tokens = $tokenRepository->findBy(["owner" => $user, "type" => $type]);

        foreach ($tokens as $token) {
            $token->setExpireAt($now);
        }
    }
}
