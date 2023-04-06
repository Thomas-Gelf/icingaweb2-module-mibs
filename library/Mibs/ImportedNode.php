<?php

namespace Icinga\Module\Mibs;

class ImportedNode implements TreeNodeInterface
{
    /** @var string */
    protected $name;

    /** @var string[] */
    protected $oidList;

    public function __construct(string $name, string $absoluteOid)
    {
        $this->name = $name;
        $this->oidList = preg_split('/\./', $absoluteOid, -1, PREG_SPLIT_NO_EMPTY);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOidPath(): array
    {
        return $this->oidList;
    }
}
