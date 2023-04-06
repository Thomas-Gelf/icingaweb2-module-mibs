<?php

namespace Icinga\Module\Mibs;

interface TreeNodeInterface
{
    public function getName(): string;
    public function getOidPath(): array;
}
