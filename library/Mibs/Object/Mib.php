<?php

namespace Icinga\Module\Mibs\Object;

use Icinga\Module\Director\Data\Db\DbObject;

class Mib extends DbObject
{
    const TABLE = 'snmp_mib';
    protected $table = self::TABLE;
    protected $keyName = 'mib_checksum';

    protected $defaultProperties = [
        'mib_checksum'    => null,
        'mib_name'        => null,
        'short_name'      => null,
        'smi_version'     => null, // 1, 2
        'last_updated'    => null,
        'ts_last_updated' => null,
        'organization'    => null,
        'contact_info'    => null,
        'description'     => null,
    ];
}
