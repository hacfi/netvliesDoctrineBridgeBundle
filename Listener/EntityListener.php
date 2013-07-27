<?php
/*
 * This file is part of the Netvlies DoctrineBridgeBundle
 *
 * (c) Netvlies Internetdiensten
 * author: M. de Krijger <mdekrijger@netvlies.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Netvlies\Bundle\DoctrineBridgeBundle\Listener;


use Doctrine\Bundle\PHPCRBundle\ManagerRegistry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\UnitOfWork;
use Metadata\MetadataFactoryInterface;
use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EntityListener
{
    protected $metadataFactory;

    protected $doctrine;


    public function __construct(MetadataFactoryInterface $metadataFactory, ManagerRegistry $doctrine)
    {
        $this->doctrine        = $doctrine;
        $this->metadataFactory = $metadataFactory;
    }


    public function preUpdate(LifecycleEventArgs $args)
    {
        $this->prePersist($args);
    }


    public function prePersist(LifecycleEventArgs $args)
    {
        $entity                 = $args->getEntity();
        $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($entity));
        $classMetadata          = $classHierarchyMetadata->classMetadata[get_class($entity)];

        foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

            if ($propertyMetadata->type === 'odm') {

                $dm = $this->doctrine->getManager($propertyMetadata->targetManager);

                list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                $realClassName = $dm->getConfiguration()->getDocumentNamespace($namespaceAlias).'\\'.$simpleClassName;

                /** @var ClassMetadata $documentMetaData */
                $documentMetaData = $dm->getClassMetadata($realClassName);
                $document         = $propertyMetadata->getValue($entity);

                if (is_null($document)) {
                    continue;
                }

                $idValues = $documentMetaData->getIdentifierValues($document);
                $propertyMetadata->setValue($entity, serialize($idValues));

                if ($args->getEntityManager()->getUnitOfWork()->getEntityState($entity) === UnitOfWork::STATE_MANAGED) {
                    $class = $args->getEntityManager()->getClassMetadata(get_class($entity));
                    $args->getEntityManager()->getUnitOfWork()->recomputeSingleEntityChangeSet($class, $entity);
                }
            }
        }
    }


    public function postLoad(LifecycleEventArgs $args)
    {
        $entity                 = $args->getEntity();
        $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($entity));

        $classMetadata = $classHierarchyMetadata->classMetadata[get_class($entity)];

        foreach ($classHierarchyMetadata->classMetadata as $classMetadata) {
            foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

                /* @var $propertyMetadata \Netvlies\Bundle\DoctrineBridgeBundle\Mapping\PropertyMetadata */
                $reference = null;

                if ($propertyMetadata->type === 'odm') {
                    $dm = $this->doctrine->getManager($propertyMetadata->targetManager);

                    list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                    $realClassName = $dm->getConfiguration()->getDocumentNamespace($namespaceAlias).'\\'.$simpleClassName;

                    /** @var ClassMetadata $documentMetaData */
                    $documentMetaData = $dm->getClassMetadata($realClassName);
                    $value            = $propertyMetadata->getValue($entity);

                    if (empty($value)) {
                        continue;
                    }

                    // This means we only have support for simple relations pointing to one id.
                    $ids = unserialize($value);
                    if (is_array($ids) && !empty($ids)) {
                        $id  = array_shift($ids);

                        $reference = $dm->getReference($realClassName, $id);
                    }
                } else {
                    continue;
                }

                if (!is_null($reference)) {
                    $propertyMetadata->setValue($entity, $reference);
                }
            }
        }
    }
}
