<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Module\Mibs\Object\MibImport;
use Icinga\Module\Mibs\Object\Node;

class MissingMibsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'source_mib_name',
    ];

    public function getColumnsToBeRendered(): array
    {
        return array(
            $this->translate('Missing MIB Name'),
            $this->translate('Depending MIBs'),
        );
    }

    public function renderRow($row)
    {
        return static::row([
            $row->source_mib_name,
            $row->cnt_dependening_mibs
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['smi' => MibImport::TABLE], [
                'source_mib_name'      => 'smi.source_mib_name',
                'cnt_dependening_mibs' => 'COUNT(DISTINCT smi.mib_checksum)'
            ])->joinLeft(
                ['smn' => Node::TABLE],
                'smi.mib_checksum = smn.mib_checksum AND smi.object_name = smn.object_name AND smn.oid',
                []
            )->where('smn.oid IS NULL')
            ->group('smi.source_mib_name')
            ->order('cnt_dependening_mibs DESC')
            ->order('source_mib_name');
    }
}
