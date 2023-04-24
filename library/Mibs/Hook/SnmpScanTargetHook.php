<?php

namespace Icinga\Module\Mibs\Hook;

use React\EventLoop\LoopInterface;

/**
 * WARNING: do not implement this Hook, it will change soon
 */
abstract class SnmpScanTargetHook
{
    abstract public function enumTargets(): array;
    // TODO: data type for credential
    abstract public function getCredential(string $targetIdentifier): string;
    abstract public function getDestination(string $targetIdentifier): string;
    abstract public function getRemoteSnmpClient(string $targetIdentifier, LoopInterface $loop);
}
