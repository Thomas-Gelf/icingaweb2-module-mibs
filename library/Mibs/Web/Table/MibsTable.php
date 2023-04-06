<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use ipl\Html\Html;

class MibsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'sm.mib_name',
        'sm.short_name',
        'sm.organization',
    ];

    protected $organization;

    public function getColumnsToBeRendered(): array
    {
        return [
            $this->translate('MIB name'),
            // $this->translate('State'),
        ];
    }

    public function filterOrganization($organization)
    {
        $this->organization = $organization;
    }

    public function renderRow($row)
    {
        return static::row([
            [
                Link::create(
                    self::label($row),
                    'mibs/mib',
                    ['checksum' => bin2hex($row->mib_checksum)]
                ),
                $this->getAdditionalInfo($row),
            ],
            // sprintf('%s / %s', $row->cnt_processed ?? '-', ($row->cnt_processed + $row->cnt_pending) ?? '-'),
        ]);
    }

    public static function label($row)
    {
        if (! isset($row->mib_name) && isset($row->original_filename)) { // NEVER
            return $row->original_filename;
        }
        $label = $row->mib_name;
        if (isset($row->short_name) && $row->short_name !== $row->mib_name) {
            $label .= ' (' . $row->short_name . ')';
        }

        return $label;
    }

    protected function getAdditionalInfo($row)
    {
        if (! $this->organization && isset($row->organization)) {
            return [Html::tag('br'), Html::tag('i', $row->organization)];
        }

        return null;
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()
            ->from(
                ['sm' => 'snmp_mib'],
                [
                    'sm.mib_name',
                    'sm.mib_checksum',
                    'sm.organization',
                    'sm.short_name',
                    /*
                    'cnt_pending' => 'SUM(CASE WHEN smn.mib_checksum IS NULL THEN NULL ELSE'
                        . ' CASE WHEN smn.oid IS NULL THEN 1 ELSE 0 END END)',
                    'cnt_processed' => 'SUM(CASE WHEN smn.mib_checksum IS NULL THEN NULL ELSE'
                        . ' CASE WHEN smn.oid IS NULL THEN 0 ELSE 1 END END)',
                    */
                ]
            )/*->joinLeft(
                ['smn' => Node::TABLE],
                'smn.mib_checksum = sm.mib_checksum',
                []
            )*/->group('sm.mib_checksum')->order('mib_name')->limit(40);
        if ($this->organization) {
            if ($this->organization === MibOrganizationsTable::NULL) {
                $query->where('sm.organization IS NULL');
            } else {
                $query->where('sm.organization = ?', $this->organization);
            }
        }

        return $query;
    }
}
