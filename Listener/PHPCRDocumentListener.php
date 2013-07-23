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


use Doctrine\Common\Annotations\Reader;
use Metadata\MetadataFactoryInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;

use Doctrine\ODM\PHPCR\Event\LifecycleEventArgs;
use Doctrine\ODM\PHPCR\Event\PreFlushEventArgs;
use Doctrine\ODM\PHPCR\Event\PostFlushEventArgs;

class PHPCRDocumentListener
{

    protected $metadataFactory;

    protected $doctrine;


    public function __construct(MetadataFactoryInterface $metadataFactory, Registry $doctrine)
    {
        $this->doctrine        = $doctrine;
        $this->metadataFactory = $metadataFactory;
    }


    public function preFlush(PreFlushEventArgs $args)
    {
        $dm         = $args->getDocumentManager();
        $unitOfWork = $dm->getUnitOfWork();

        foreach ($dm->getRepository('GlamourRent\AppBundle\Document\ProductProperties')->findAll() as $document) {
            if ($unitOfWork->contains($document)) {
                $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($document));
                $classMetadata          = $classHierarchyMetadata->classMetadata[get_class($document)];

                foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

                    if ($propertyMetadata->type === 'dbal') {

                        $em = $this->doctrine->getManager($propertyMetadata->targetManager);
                        // Copied following two lines from Doctrine\ORM\Mapping\ClassMetadataFactory
                        list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                        $realClassName = $em->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;

                        /** @var \Doctrine\ORM\Mapping\ClassMetadata $entityMetaData */
                        $entityMetaData = $em->getClassMetadata($realClassName);
                        $entity         = $propertyMetadata->getValue($document);

                        if (!is_object($entity)) {
                            continue;
                        }

                        $idValues = $entityMetaData->getIdentifierValues($entity);
                        //$idValues['uid'] = microtime().rand(0, 1000);
                        $propertyMetadata->setValue($document, serialize($idValues));
                    }
                }
            }
//            $state = $this->getDocumentState($document);
//            if ($state === self::STATE_MANAGED) {
//                $class = $this->dm->getClassMetadata(get_class($document));
//                $this->computeChangeSet($class, $document);
//            }
        }


        return;
    }


    public function preUpdate(LifecycleEventArgs $args)
    {
        $this->prePersist($args);
    }


    public function prePersist(LifecycleEventArgs $args)
    {
        $document               = $args->getDocument();
        $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($document));
        $classMetadata          = $classHierarchyMetadata->classMetadata[get_class($document)];

        foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

            if ($propertyMetadata->type === 'dbal') {

                $em = $this->doctrine->getManager($propertyMetadata->targetManager);
                // Copied following two lines from Doctrine\ORM\Mapping\ClassMetadataFactory
                list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                $realClassName = $em->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;

                /** @var \Doctrine\ORM\Mapping\ClassMetadata $entityMetaData */
                $entityMetaData = $em->getClassMetadata($realClassName);
                $entity         = $propertyMetadata->getValue($document);

                if (!is_object($entity)) {
                    continue;
                }

                $idValues = $entityMetaData->getIdentifierValues($entity);
                //$idValues['uid'] = microtime().rand(0, 1000);
                $propertyMetadata->setValue($document, serialize($idValues));
            }
        }
    }


    public function postLoad(LifecycleEventArgs $args)
    {
        $document               = $args->getDocument();
        $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($document));

        $classMetadata = $classHierarchyMetadata->classMetadata[get_class($document)];


        foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

            /* @var $propertyMetadata \Netvlies\Bundle\DoctrineBridgeBundle\Mapping\PropertyMetadata */
            $reference = null;

            if ($propertyMetadata->type === 'dbal') {

                $em = $this->doctrine->getManager($propertyMetadata->targetManager);
                // Copied following two lines from Doctrine\ORM\Mapping\ClassMetadataFactory
                list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                $realClassName = $em->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;

                /** @var \Doctrine\Common\Persistence\Mapping\ClassMetadata $entityMetaData */
                $entityMetaData = $em->getClassMetadata($realClassName);
                $value          = $propertyMetadata->getValue($document);

                if (empty($value)) {
                    continue;
                }

                // This means we only have support for simple relations pointing to one id.
                $ids = unserialize($value);
                if (is_array($ids) && !empty($ids)) {
                    $id  = array_shift($ids);

                    $reference = $em->getReference($realClassName, $id);
                }
            }

            if (!is_null($reference)) {
                $propertyMetadata->setValue($document, $reference);
            }
        }
    }


    public function postFlush(PostFlushEventArgs $args)
    {
        $dm         = $args->getDocumentManager();
        $unitOfWork = $dm->getUnitOfWork();

        foreach ($dm->getRepository('GlamourRent\AppBundle\Document\ProductProperties')->findAll() as $document) {
            if ($unitOfWork->contains($document)) {
                $classHierarchyMetadata = $this->metadataFactory->getMetadataForClass(get_class($document));
                $classMetadata = $classHierarchyMetadata->classMetadata[get_class($document)];

                foreach ($classMetadata->propertyMetadata as $propertyMetadata) {

                    /* @var $propertyMetadata \Netvlies\Bundle\DoctrineBridgeBundle\Mapping\PropertyMetadata */
                    $reference = null;

                    if ($propertyMetadata->type === 'dbal') {

                        $em = $this->doctrine->getManager($propertyMetadata->targetManager);
                        // Copied following two lines from Doctrine\ORM\Mapping\ClassMetadataFactory
                        list($namespaceAlias, $simpleClassName) = explode(':', $propertyMetadata->targetObject);
                        $realClassName = $em->getConfiguration()->getEntityNamespace($namespaceAlias).'\\'.$simpleClassName;

                        /** @var \Doctrine\Common\Persistence\Mapping\ClassMetadata $entityMetaData */
                        $entityMetaData = $em->getClassMetadata($realClassName);
                        $value          = $propertyMetadata->getValue($document);

                        if (empty($value)) {
                            continue;
                        }

                        // This means we only have support for simple relations pointing to one id.
                        $ids = unserialize($value);
                        if (is_array($ids) && !empty($ids)) {
                            $id  = array_shift($ids);

                            $reference = $em->getReference($realClassName, $id);
                        }
                    }

                    if (!is_null($reference)) {
                        $propertyMetadata->setValue($document, $reference);
                    }
                }
            }
        }
    }
}
