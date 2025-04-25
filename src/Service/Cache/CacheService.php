<?php

namespace App\Service\Cache;

use App\Entity\Statut;
use App\Entity\Type;
use App\Service\DateTimeService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Throwable;
use WiiCommon\Helper\Stream;

class CacheService {

    private const CACHE_KEY_KEYS = "__wiilog_cache_keys";
    private const CACHE_KEY_SEPARATOR = '/';

    private FilesystemAdapter $cache;

    public function __construct(
        private ExceptionLoggerService $exceptionLoggerService,
    ) {
        $this->cache = new FilesystemAdapter(
            "cache",
            3 * DateTimeService::SECONDS_IN_DAY,
        );
    }

    /**
     * Return the value of the requested item in teh cache.
     * If item is not in the cache then we return result of the callback function or null if it is not defined.
     *
     * @param ?callable(): mixed $generateValue
     * @return mixed
     */
    public function get(CacheNamespaceEnum $namespace,
                        string             $key,
                        ?callable          $generateValue = null): mixed {
        try {
            $cacheKey = $this->getCacheKey($namespace->value, $key);
            $this->saveCacheKey($cacheKey);
            return $this->cache->get($cacheKey, $generateValue ?? static fn () => null);
        } catch (Throwable $exception) {
            $this->exceptionLoggerService->sendLog($exception);
            return !$generateValue ? null : $generateValue();
        }
    }

    /**
     * Set the given value to the requested item in the cache.
     */
    public function set(CacheNamespaceEnum $namespace,
                        string             $key,
                        mixed              $value = null): void {
        $this->delete($namespace, $key);
        $this->get($namespace, $key, static fn () => $value);
    }

    /**
     * Delete requested item in the cache.
     */
    public function delete(CacheNamespaceEnum $namespace,
                           ?string            $key = null): void {
        if ($key) {
            $cacheKey = $this->getCacheKey($namespace->value, $key);
            $this->cache->delete($cacheKey);
        }
        // we remove all keys in given namespace
        else {
            $cacheKeySeparator = self::CACHE_KEY_SEPARATOR;

            // required "\\" in regex
            $cacheKeysToDelete = Stream::from($this->cache->get(self::CACHE_KEY_KEYS, static fn() => []))
                ->filter(static fn(string $key) => preg_match("/^{$namespace->value}\\$cacheKeySeparator.+/", $key))
                ->toArray();
            foreach ($cacheKeysToDelete as $cacheKey) {
                $this->cache->delete($cacheKey);
            }
            $this->deleteCacheKeys($cacheKeysToDelete);
        }
    }

    /**
     * Clear all cache.
    */
    public function clear(): void {
        $this->cache->clear();
        $this->cache->prune();
    }

    /**
     * This method let us get a setting entity without any database request.
     * $keys parameters is a set of key associated to the requested entity.
     * If the id of this entity is known in cache we call the EntityManagerInterface::getReference to create a related object.
     * Else by a database request we get the requested entity and we save its id in the cache (single case of database request).
     * A setting entity could be a Status or a Type which don't change (or rarely).
     *
     * If the requested entity is not known in database we return null
     *
     * @param class-string<T> $class Class name of requested entity
     * @param string|int ...$keys Set of parameters associated to the requested entity
     *
     * @return T|null
     *@see EntityManagerInterface::getReference()
     *
     * @template T
     */
    public function getEntity(EntityManagerInterface $entityManager,
                              string                 $class,
                              string|int             ...$keys): mixed {

        if (empty($keys)) {
            throw new \Exception("Invalid usage: you should pass keys associated to the entity");
        }

        $dictionaryCacheKey = str_replace("\\", "_", $class);
        $entityDictionary = $this->get(CacheNamespaceEnum::ENTITIES_DICTIONARY, $dictionaryCacheKey, fn() => null) ?: [];

        $keyInDictionary = Stream::from($keys)->join('_');
        $savedInCache = array_key_exists($keyInDictionary, $entityDictionary);
        $entityId = $entityDictionary[$keyInDictionary] ?? null;

        if (!$savedInCache) {
            $entity = $this->getEntityInDatabase($entityManager, $class, ...$keys);

            $entityDictionary[$keyInDictionary] = $entity?->getId();
            $this->set(CacheNamespaceEnum::ENTITIES_DICTIONARY, $dictionaryCacheKey, $entityDictionary);

            return $entity;
        }

        return $entityId
            ? $entityManager->getReference($class, $entityId)
            : null;
    }

    /**
     * Method associated to self::getEntity
     * Retrieve entity corresponding to $keys parameters (set of key associated to the requested entity).
     * Call right method of the right repository for the requested class
     *
     * @template T
     * @param class-string<T> $class Class name of requested entity
     * @param string|int ...$keys Set of parameters associated to the requested entity
     *
     * @return T|null
     */
    private function getEntityInDatabase(EntityManagerInterface $entityManager,
                                         string                 $class,
                                         string|int             ...$keys): mixed {
        $statusRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        return match ($class) {
            Statut::class => $statusRepository->findOneByCategorieNameAndStatutCode(...$keys),
            Type::class => $typeRepository->findOneByCategoryLabelAndLabel(...$keys),
            default       => throw new \Exception('Unavailable $class parameter given'),
        };
    }


    private function getCacheKey(string $namespace,
                                 string $key): string {
        return $namespace . self::CACHE_KEY_SEPARATOR . $key;
    }

    private function saveCacheKey(string $cacheKey): void {
        $cacheKeys = $this->cache->get(self::CACHE_KEY_KEYS, static fn() => []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            $this->cache->delete(self::CACHE_KEY_KEYS);

            // save new cache keys collection
            $this->cache->get(self::CACHE_KEY_KEYS, static fn() => $cacheKeys);
        }
    }

    private function deleteCacheKeys(array $cacheKeysToRemove): void {
        $cacheKeys = $this->cache->get(self::CACHE_KEY_KEYS, static fn() => []);

        $this->cache->delete(self::CACHE_KEY_KEYS);

        // save new cache keys collection
        $this->cache->get(self::CACHE_KEY_KEYS, static fn() => array_diff($cacheKeys, $cacheKeysToRemove));
    }
}
