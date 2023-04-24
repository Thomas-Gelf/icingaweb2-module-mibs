<?php

namespace Icinga\Module\Mibs\Object;

use Icinga\Module\Director\Data\Db\DbObject;

class MibNode extends DbObject
{
    const TABLE = 'snmp_mib_node';
    protected $table = self::TABLE;
    protected $keyName = ['mib_checksum', 'object_name'];

    protected $defaultProperties = [
        'mib_checksum'    => null,
        'object_name'        => null,
        'parent_name'        => null,
        'relative_oid'        => null,
        'macro'        => null,
        'oid'        => null,
        'oid_uuid'        => null,
        'depth'        => null,
        'description'        => null,
        'units'        => null,
        'access'        => null,
        'status'        => null,
        'default_value'        => null,
        'reference'        => null,
        'display_hint'        => null,
        'syntax'        => null,
        'table_index'        => null,
        'items'        => null,
        'objects'        => null,
    ];
}
