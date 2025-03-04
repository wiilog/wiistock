<?php

namespace App\Service;

use App\Entity\Statut;
use App\Helper\FileSystem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use WiiCommon\Helper\Stream;

class CacheService
{

    private const CACHE_FOLDER = "/cache";

    public const COLLECTION_PERMISSIONS = "permissions";
    public const COLLECTION_TRANSLATIONS = "translations";
    public const COLLECTION_LANGUAGES = "languages";
    public const COLLECTION_EXPORTS = "exports";
    public const COLLECTION_IMPORTS = "imports";
    public const COLLECTION_PURCHASE_REQUEST_PLANS = "purchase-request-plans";
    public const COLLECTION_INVENTORY_MISSION_PLANS = "inventory-mission-plans";
    public const COLLECTION_SETTINGS = "settings";
    public const COLLECTION_WORK_PERIOD = "work-period";
    public const COLLECTION_ENTITIES_DICTIONARY = "entities";

    private FileSystem $filesystem;

    public function __construct(KernelInterface $kernel) {
        $this->filesystem = new FileSystem($kernel->getProjectDir() . self::CACHE_FOLDER);
    }

    /**
     * Return the value of the requested item in teh cache.
     * If item is not in the cache then we return result of the callback function or null if it is not defined.
     *
     * @param ?callable(): mixed $generateValue
     * @return mixed
     */
    public function get(string    $namespace,
                        string    $key,
                        ?callable $generateValue = null): mixed
    {
        $cacheExists = $this->filesystem->exists("$namespace/$key");

        if ($cacheExists || $generateValue) {
            if (!$cacheExists) {
                $this->set($namespace, $key, $generateValue());
            }

            try {
                $result = unserialize($this->filesystem->getContent("$namespace/$key"));
            }
            catch(Throwable) {
                $result = null;
            }

        }
        else {
            $result = null;
        }

        return $result;

    }

    /**
     * Set the given value to the requested item in the cache.
     */
    public function set(string $namespace,
                        string $key,
                        mixed  $value = null): void {
        if (!$this->filesystem->isDir()) {
            $this->clear();
        }

        if(!$this->filesystem->isFile("$namespace/$key")) {
            $this->filesystem->remove("$namespace/$key");
        }

        $this->filesystem->dumpFile("$namespace/$key", serialize($value));
    }

    /**
     * Delete requested item in the cache.
     */
    public function delete(string $namespace, ?string $key = null): void {
        if ($this->filesystem->exists("$namespace/$key")) {
            $this->filesystem->remove("$namespace/$key");
        }
    }

    /**
     * Clear all cache directory.
     */
    public function clear(): void {
        if ($this->filesystem->exists()) {
            $dirFinder = new Finder();
            $dirFinder
                ->depth('== 0')
                ->ignoreDotFiles(true)
                ->in($this->filesystem->getRoot());

            if ($dirFinder->hasResults()) {
                foreach ($dirFinder as $directory) {
                    $fs = new FileSystem($directory);
                    $fs->remove();
                }
            }
        }
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
        $entityDictionary = $this->get(self::COLLECTION_ENTITIES_DICTIONARY, $dictionaryCacheKey) ?: [];

        $keyInDictionary = Stream::from($keys)->join('_');
        $savedInCache = array_key_exists($keyInDictionary, $entityDictionary);
        $entityId = $entityDictionary[$keyInDictionary] ?? null;

        if (!$savedInCache) {
            $entity = $this->getEntityInDatabase($entityManager, $class, ...$keys);

            $entityDictionary[$keyInDictionary] = $entity?->getId();
            $this->set(self::COLLECTION_ENTITIES_DICTIONARY, $dictionaryCacheKey, $entityDictionary);

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

        return match ($class) {
            Statut::class => $statusRepository->findOneByCategorieNameAndStatutCode(...$keys),
            default       => throw new \Exception('Unavailable $class parameter given'),
        };
    }
}
