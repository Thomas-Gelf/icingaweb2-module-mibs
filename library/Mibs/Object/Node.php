<?php

namespace Icinga\Module\Mibs\Object;

use gipfl\Json\JsonString;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Formatting;
use Ramsey\Uuid\Uuid;

class Node extends DbObject
{
    const TABLE = 'snmp_mib_node';
    protected $table = self::TABLE;
    protected $keyName = ['mib_checksum', 'object_name'];

    protected $defaultProperties = [
        'mib_checksum' => null,
        'object_name'  => null,
        'parent_name'  => null,
        'relative_oid' => null,
        'macro'        => null, // type
        'oid'          => null,
        'oid_uuid'     => null,
        'depth'        => null,

        'description'   => null,
        'units'         => null,
        'access'        => null,
        'status'        => null,
        'default_value' => null,
        'reference'     => null,
        'display_hint'  => null,
        'syntax'        => null,
        'table_index'   => null,
        'items'         => null,
        'objects'       => null,
    ];

    public function insert(Db $db)
    {
        $this->setConnection($db);
        $this->insertIntoDb();
        $this->setBeingLoadedFromDb();
    }

    public static function fromParsedNode(MibFile $mibFile, $nodeName, $node): Node
    {
        $ns = Uuid::fromString(Uuid::NAMESPACE_OID);
        // for more than two array entries, please see RFC1155-SMI:
        $oid = $node->oid;
        $relativeOid = array_pop($oid); // 1 or higher
        $parentName = array_pop($oid); // 0 or higher
        $self = Node::create([
            'mib_checksum' => $mibFile->get('mib_checksum'),
            'object_name'  => $nodeName,
            'parent_name'  => $parentName,
            'relative_oid' => $relativeOid,
            'macro'        => $node->type ?? 'OBJECT IDENTIFIER',
            'oid'          => $node->oidString ?? null,
            'oid_uuid'     => isset($node->oidString) ? Uuid::uuid5($ns, $node->oidString)->getBytes() : null,
            'depth'        => isset($node->oidString) ? (count(explode('.', $node->oidString)) - 1) : null,
            // -> uuid
        ]);
        foreach ([
            'description'   => $node->description ?? null,
            'units'         => $node->units ?? null,
            'access'        => $node->access ?? null,
            'status'        => $node->status ?? null,
            'defval'        => $node->defval ?? null,
            'reference'     => $node->reference ?? null,
            'display-hint'  => $node->{'display-hint'} ?? null,
            'syntax'        => $node->syntax ?? null,
            'index'         => $node->index ?? null,
            'items'         => $node->items ?? null,
            'objects'       => $node->objects ?? null,
            // TODO: parent_uuid
        ] as $key => $value) {
            $self->setFromNode($key, $value);
        }

        return $self;
    }

    public function setFromNode($key, $value)
    {
        // TODO: OBJECT-GROUP -> objects (CISCO-ENTITY-EXT-MIB)
        $modifiers = [
            'description'  => [Formatting::class, 'stringCleanup'],
            'units'        => [Formatting::class, 'stringCleanup'],
            'reference'    => [Formatting::class, 'stringCleanup'],
            'index'        => [$this, 'jsonEncode'],
            'items'        => [$this, 'jsonEncode'],
            'objects'      => [$this, 'jsonEncode'],
            'syntax'       => [$this, 'jsonEncode'],
        ];
        $nameMapping = [
            'index'        => 'table_index',
            'defval'       => 'default_value',
            'display-hint' => 'display_hint',
        ];
        if (isset($modifiers[$key])) {
            $method = $modifiers[$key];
            $value = $method($value);
        }

        if (isset($nameMapping[$key])) {
            $key = $nameMapping[$key];
        }

        parent::set($key, $value);
    }

    protected static function jsonEncode($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return JsonString::encode($value);
    }
}
