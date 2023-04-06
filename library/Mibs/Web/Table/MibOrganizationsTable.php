<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class MibOrganizationsTable extends ZfQueryBasedTable
{
    const NULL = '__NULL__';

    protected $searchColumns = [
        'sm.organization',
    ];

    public function getColumnsToBeRendered(): array
    {
        return [
            $this->translate('Organization'),
            $this->translate('MIBs'),
        ];
    }

    public function renderRow($row)
    {
        return static::row([
            Link::create(
                $row->organization ?: $this->translate('(none)'),
                'mibs/mibs',
                ['organization' => $row->organization ?: self::NULL]
            ),
            $row->cnt_mibs,
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(['sm' => 'snmp_mib'], [
            'sm.organization',
            'cnt_mibs' => 'COUNT(*)'
        ])->group('sm.organization')->order('sm.organization')->limit(40);
    }
}
