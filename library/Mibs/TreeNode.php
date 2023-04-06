<?php

namespace Icinga\Module\Mibs;

use RuntimeException;

class TreeNode implements TreeNodeInterface
{
    /** @var string */
    protected $name;
    /** @var ?TreeNodeInterface */
    protected $parent = null;
    /** @var string */
    protected $relativeOid;
    protected $children = [];

    public function __construct(string $name, string $relativeOid, ?TreeNode $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->relativeOid = $relativeOid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setParent(TreeNodeInterface $node): void
    {
        if ($this->parent === null) {
            $this->parent = $node;
        } else {
            throw new RuntimeException(sprintf(
                'Cannot set tree parent twice, got "%s" while already having "%s" for "%s"',
                $node->name,
                $this->parent->name,
                $this->name
            ));
        }
    }

    public function getOidPath(): array
    {
        if ($this->parent === null) {
            return [$this->relativeOid];
        }

        $path = $this->parent->getOidPath();
        $path[] = $this->relativeOid;

        return $path;
    }
}
