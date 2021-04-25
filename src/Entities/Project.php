<?php declare(strict_types=1);

namespace doctrine\EntityViewer\Entities;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Persistence\ObjectManager;
use function Doctrine\ORM\QueryBuilder;

class Project
{
    const DOCTRINE_ORM = 'doctrine/orm';
    const TYPES = [
        self::DOCTRINE_ORM => true,
    ];

    /** @var Configuration */
    protected $configuration;
    /** @var string */
    public $name;
    /** @var string */
    public $autoloadFile;
    /** @var string */
    public $databaseConnectionString;
    /** @var array<string, string> */
    public $resolveTargetEntities;
    /** @var bool */
    public $isDevMode;
    /** @var string */
    public $type = self::DOCTRINE_ORM;
    /** @var string[] */
    public $entityDirectories = [];
    /** @var string[] */
    public $entities = [];
    /** @var ObjectManager */
    public $objectManager;

    public function __construct(
        Configuration $configuration,
        string $name,
        string $autoloadFile = '/absolute/path/to/vendor/autoload.php',
        string $databaseConnectionString = 'mysql://user:password@127.0.0.1:3306/database',
        array $resolveTargetEntities = [],
        bool $isDevMode = true,
        array $entityDirectories = []
    )
    {
        $this->configuration = $configuration;
        $this->configuration->projects[$name] = $this;
        $this->name = $name;
        $this->autoloadFile = $autoloadFile;
        $this->databaseConnectionString = $databaseConnectionString;
        $this->resolveTargetEntities = $resolveTargetEntities;
        $this->isDevMode = $isDevMode;

        if (empty($entityDirectories)) {
            $entityDirectories[] = dirname($autoloadFile, 2).'/src/Entity';
            $entityDirectories[] = dirname($autoloadFile, 2).'/vendor/brightside/brightside-bundle/src/Entity';
        }

        $this->entityDirectories = $entityDirectories;
    }

    public function getEntities(string $fQCN, array $constrains, int $page): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->objectManager;
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('alias')
            ->from($fQCN, 'alias');

        foreach ($constrains as $name => $constrain) {
            if (is_array($constrain)) {
                $queryBuilder->andWhere($queryBuilder->expr()->in("alias.$name", $constrain));
            } elseif (is_string($constrain) && false !== strpos($constrain, '%')) {
                $queryBuilder->andWhere($queryBuilder->expr()->like("alias.$name", ":$name"));
                $queryBuilder->setParameter($name, $constrain);
            } else {
                $queryBuilder->andWhere("alias.$name=:$name");
                $queryBuilder->setParameter($name, $constrain);
            }
        }

        $query = $queryBuilder->getQuery();

        if ($page >= 0) {
            $limit = $this->configuration->resultsPerPage;

            if ($limit > 0) {
                $query->setFirstResult($page * $limit);
                $query->setMaxResults($limit);
            }
        }

        return $query->getResult();
    }

    public function initObjectManager(): void
    {
        require_once $this->autoloadFile;

        $config = Setup::createAnnotationMetadataConfiguration(
            $this->entityDirectories,
            $this->isDevMode,
            $this->configuration->dataDirectory.'/cache/doctrine/'.$this->name,
            new ArrayCache(),
            false
        );

        $docParser = new DocParser();
        $docParser->setIgnoreNotImportedAnnotations(true);
        $annotationReader = new AnnotationReader($docParser);
        $reader = new CachedReader(new IndexedReader($annotationReader), new ArrayCache());

        $annotationDriver = new AnnotationDriver($reader);
        $annotationDriver->addPaths($this->entityDirectories);
        $config->setMetadataDriverImpl($annotationDriver);

        $conn = [
            'url' => $this->databaseConnectionString,
        ];
        $entityManager = EntityManager::create($conn, $config, $this->getEventManager());
        $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $this->objectManager = $entityManager;
    }

    protected function getEventManager(): EventManager
    {
        $evm  = new EventManager;
        $rtel = new ResolveTargetEntityListener;
        foreach ($this->resolveTargetEntities as $originalEntity => $targetEntity) {
            $rtel->addResolveTargetEntity($originalEntity, $targetEntity, array());
        }
        $evm->addEventListener(Events::loadClassMetadata, $rtel);

        return $evm;
    }

    public function initEntities(): void
    {
        $classNames = [];
        foreach ($this->objectManager->getMetadataFactory()->getAllMetadata() as $metadata)
        {
            $arr = explode('\\', $metadata->getName());
            $classNames[$metadata->getName()] = array_pop($arr);
        }
        asort($classNames);

        $this->entities = $classNames;
    }
}
