<?php

namespace Icinga\Module\Mibs;

use RuntimeException;

class MibTree
{
    protected $name;
    protected $root;
    protected $roots = [];
    protected $paths = [];
    /**
     * @var false
     */
    protected $allowMultiRoot;

    public function __construct($parsedMib, $allowMultiRoot = false)
    {
        $this->allowMultiRoot = $allowMultiRoot;
        $this->name = $parsedMib->name ?? 'ERR: Unknown name';
        $tree = $parsedMib->tree;
        if (empty((array) $tree)) {
            return;
        }
        $root = null;
        $clone = [];
        $seen = [];

        foreach ($tree as $key => $members) {
            foreach ($members as $id => $member) {
                if (property_exists($tree, $member)) {
                    $seen[$member] = $member;
                }
            }
        }

        foreach ($tree as $key => $members) {
            if (! array_key_exists($key, $seen)) {
                if ((string) $key === '0') {
                    // zeroDotZero
                    continue;
                }
                if ($root === null) {
                    $root = $key;
                } elseif ($this->allowMultiRoot) {
                    $root = (array)$root;
                    $root[] = $key;
                } else {
                    // SNMPv2-MIB:
                    // Got more than one root key, 'mib-2' and 'snmpModules'
                    throw new RuntimeException(
                        "Got more than one root key, '$root' and '$key'. This is not yet supported"
                    );
                }
            }
        }

        if ($root === null) {
            throw new RuntimeException('Got no root node');
        }

        $roots = (array) $root;
        $this->roots = [];
        foreach ($roots as $root) {
            $clone[$root] = ['name' => $root, 'children' => [], 'path' => ".$root", 'oid' => ".$root"];

            static::getMembers($clone[$root]['children'], $tree->$root, $tree, ".$root", ".$root", $this);

            $this->roots[] = $clone[$root];
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRoot()
    {
        if (count($this->roots) === 1) {
            return current($this->roots);
        }

        throw new RuntimeException('I have multiple roots');
    }

    public function getRoots()
    {
        return $this->roots;
    }

    public function isEmpty(): bool
    {
        return $this->root === null;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    protected static function getMembers(&$clone, $subTree, $tree, $namePath, $oidPath, MibTree $self)
    {
        foreach ($subTree as $id => $key) {
            $oid = "$oidPath.$id";
            $names = "$namePath.$key";
            $self->paths[$key] = $oid;
            if (property_exists($tree, $key)) {
                $clone[$key] = [
                    'name'     => $key,
                    'oid'      => $oid,
                    'path'     => $names,
                    'children' => []
                ];

                static::getMembers($clone[$key]['children'], $tree->$key, $tree, $names, $oid, $self);
            } else {
                $clone[$key] = [
                    'name'     => $key,
                    'oid'      => $oid,
                    'path'     => $names,
                ];
            }
        }
    }
}
