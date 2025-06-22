<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

enum DbEvents: string
{
    case preRemove = 'preRemove';
    case postRemove = 'postRemove';
    case prePersist = 'prePersist';
    case postPersist = 'postPersist';
    case preUpdate = 'preUpdate';
    case postUpdate = 'postUpdate';
    case postLoad = 'postLoad';
    case loadClassMetadata = 'loadClassMetadata';
    case onClassMetadataNotFound = 'onClassMetadataNotFound';
    case preFlush = 'preFlush';
    case onFlush = 'onFlush';
    case postFlush = 'postFlush';
    case onClear = 'onClear';
    case preStateTransition = 'preStateTransition';
    case postStateTransition = 'postStateTransition';
}
