<?php

namespace Icinga\Module\Mibs\Object;

use Icinga\Module\Director\Data\Db\DbObject;

class MibImport extends DbObject
{
    const TABLE = 'snmp_mib_import';
    protected $table = self::TABLE;
    protected $keyName = 'uuid';

    protected $defaultProperties = [
        'mib_checksum'    => null,
        'source_mib_name' => null,
        'object_name'     => null,
    ];
}
