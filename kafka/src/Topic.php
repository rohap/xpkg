<?php

namespace Xpkg\Kafka;

enum Topic: string
{
    case TEACH_FACE_LOCAL = 'localPicsFaceprintTeach';
    case TEACH_FACE_EXTERNAL = 'externalPicsFaceprintTeach';
    case TEST_TOPIC = 'testTopic';
    case PROFILES = 'Profiles';
    case READY_PROFILES = 'ReadyProfiles';
}