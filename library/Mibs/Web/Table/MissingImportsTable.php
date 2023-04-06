<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Module\Mibs\Object\MibImport;
use Icinga\Module\Mibs\Object\Node;

class MissingImportsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'source_mib_name',
        'object_name',
    ];

    public function getColumnsToBeRendered(): array
    {
        return array(
            $this->translate('Missing Import'),
            $this->translate('Depending MIBs'),
        );
    }

    public function renderRow($row)
    {
        return static::row([
            $row->source_mib_name . '::' . $row->object_name,
            $row->cnt_dependening_mibs
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['smi' => MibImport::TABLE], [
                'source_mib_name'      => 'smi.source_mib_name',
                'object_name'          => 'smi.object_name',
                'cnt_dependening_mibs' => 'COUNT(*)'
            ])->joinLeft(
                ['smn' => Node::TABLE],
                'smi.mib_checksum = smn.mib_checksum AND smi.object_name = smn.object_name', //  AND smn.oid IS NULL ?? Types?
                []
            )->where('smn.oid IS NULL')
            ->group('smi.source_mib_name')
            ->group('smi.object_name')
            ->order('cnt_dependening_mibs DESC')
            ->order('smi.source_mib_name')
            ->order('smi.object_name');
    }
}
