<?php

namespace Icinga\Module\Mibs;

use InvalidArgumentException;

class MibTreeNew
{
    protected $name;
    /** @var TreeNode[] */
    protected $roots = [];
    protected $loadedImports;
    /** @var TreeNodeInterface[] */
    protected $nodes;

    public function __construct($parsedMib, $loadedImports)
    {
        $this->loadedImports = $loadedImports;
        $this->name = $parsedMib->name ?? 'ERR: Unknown name';
        $tree = $parsedMib->tree;
        if (empty((array) $tree)) {
            return;
        }
        $nodes = [];
        $parentList = [];
        $availableParents = [];
        foreach ($loadedImports as $importedName => $oid) {
            $availableParents[$importedName] = $nodes[$importedName] = new ImportedNode($importedName, $oid);
        }

        foreach ($tree as $parentName => $members) {
            foreach ($members as $relativeOid => $member) {
                $parentList[$member] = $parentName;
                $nodes[$member] = new TreeNode($member, $relativeOid);
                if (property_exists($tree, $member)) {
                    $availableParents[$member] = $member;
                }
            }
        }

        foreach ($nodes as $node) {
            if (isset($parentList[$node->getName()])) {
                $parentName = $parentList[$node->getName()];
                if (isset($nodes[$parentName])) {
                    $node->setParent($nodes[$parentName]);
                } else {
                    throw new \RuntimeException("There is no such (parent) node: '$parentName'");
                }
            }
        }

        foreach ($tree as $parent => $members) {
            if (! array_key_exists($parent, $availableParents)) {
                if ((string) $parent === '0') {
                    // zeroDotZero, it's ok
                }
                $this->roots[] = $nodes[$parent];
            }
        }

        $this->nodes = $nodes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNode(string $nodeName): TreeNodeInterface
    {
        if (isset($this->nodes[$nodeName])) {
            return $this->nodes[$nodeName];
        }

        throw new InvalidArgumentException("There is no node named '$nodeName'");
    }

    /**
     * @return TreeNode[]
     */
    public function getRootNodes(): array
    {
        return $this->roots;
    }
}
