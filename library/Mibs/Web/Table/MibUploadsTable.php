<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Object\Mib;
use ipl\Html\Html;

class MibUploadsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'sm.mib_name',
        // 'smu.original_filename',
    ];

    public function getColumnsToBeRendered(): array
    {
        return array(
            $this->translate('MIB name'),
            // $this->translate('State'),
        );
    }

    public function renderRow($row)
    {
        return static::row([
            [
                Link::create(
                    MibsTable::label($row),
                    'mibs/mib/process',
                    // ['checksum' => Uuid::fromBytes($row->mib_checksum)->toString()]
                    ['checksum' => bin2hex($row->mib_file_checksum)]
                ),
                $this->getAdditionalInfo($row),
            ],
            // sprintf('%s / %s', $row->cnt_processed ?? '-', ($row->cnt_processed + $row->cnt_pending) ?? '-'),
        ]);
    }

    protected function getAdditionalInfo($row)
    {
        if (isset($row->organization)) {
            return [Html::tag('br'), Html::tag('i', $row->organization)];
        }
        if ($row->last_processing_error) {
            return [Html::tag('br'), Html::tag('span', ['class' => 'error'], $row->last_processing_error)];
        }

        return null;
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(
                ['smf' => MibFile::TABLE],
                [
                    'sm.mib_name',
                    'smf.mib_file_checksum',
                    'smf.last_processing_error',
                    // 'smu.original_filename',
                    'sm.organization',
                    'sm.short_name',
                    // 'cnt_pending' => 'SUM(CASE WHEN smn.mib_checksum IS NULL THEN NULL ELSE'
                    //     . ' CASE WHEN smn.oid IS NULL THEN 1 ELSE 0 END END)',
                    // 'cnt_processed' => 'SUM(CASE WHEN smn.mib_checksum IS NULL THEN NULL ELSE'
                    //     . ' CASE WHEN smn.oid IS NULL THEN 0 ELSE 1 END END)',
                ]
            )->joinLeft(
                ['sm' => Mib::TABLE],
                'smf.mib_checksum = sm.mib_checksum',
                []
            )/*->joinLeft(
                ['smn' => Node::TABLE],
                'smn.mib_checksum = smf.mib_checksum',
                []
            )*/->group('smf.mib_file_checksum')->order('mib_name')->limit(20);
    }
}
