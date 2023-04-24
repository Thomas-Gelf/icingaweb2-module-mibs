<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Module\Mibs\Object\Mib;
use Icinga\Module\Mibs\Object\Node;
use ipl\Html\Html;

class NodesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'smn.object_name',
        'smn.description',
    ];

    /** @var Mib */
    protected $mib;
    /** @var int */
    protected $minDepth;

    public function __construct(Mib $mib)
    {
        parent::__construct($mib->getDb());
        $this->getAttributes()->add('style', 'width: 100%; max-width: unset');
        $db = $mib->getDb();
        $this->mib = $mib;
        $this->minDepth = (int) $db->fetchOne($this->select('MIN(smn.depth)'));
    }

    public function getColumnsToBeRendered(): array
    {
        return array(
            $this->translate('Name'),
            $this->translate('OID'),
            $this->translate('Description'),
        );
    }

    public function renderRow($row)
    {
        $mib = bin2hex($this->mib->get('mib_checksum'));
        return static::row([
            [
                Html::tag('span', ['style' => 'display: inline-block; white-space: nowrap'], [
                    Html::tag('span', [
                        'style' => sprintf('display: inline-block; width: %dem', $row->depth - $this->minDepth)
                    ]),
                    $this->macroIcon($row->macro, $row->table_index),
                    Link::create(
                        $row->object_name,
                        'mibs/mib/node',
                        [
                            'mib' => $mib,
                            'node' => $row->object_name
                        ]
                    ),
                ])
            ],
            $row->oid,
            isset($row->description)
                ? Html::tag('span', [
                    'style' => 'display: inline-block; height: 1.5em; overflow: hidden'
                  ], preg_replace('/\n/', ' ', $row->description))
                : null,
        ]);
    }

    protected function describeTableIndex($tableIndex): string
    {
        $tableIndex = json_decode($tableIndex);
        $parts = [];
        foreach ($tableIndex as $index) {
            $part = $index->value;
            if (isset($index->implied) && $index->implied) {
                $part .= ' (' . $this->translate('implied') . ')';
            }
            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    protected function macroIcon($macro, $tableIndex): Icon
    {
        $title = $macro;
        switch ($macro) {
            case 'OBJECT IDENTIFIER':
                $icon = 'down-dir';
                break;
            case 'MODULE-IDENTITY':
                $icon = 'globe';
                break;
            case 'OBJECT-TYPE':
                if ($tableIndex === null) {
                    $icon = 'service';
                } else {
                    $icon = 'th-list';
                    $title .= ', table indexed by ' . $this->describeTableIndex($tableIndex);
                }
                break;
            case 'MODULE-COMPLIANCE':
                $icon = 'check';
                break;
            case 'OBJECT-GROUP':
                $icon = 'services';
                break;
            case 'NOTIFICATION-GROUP':
                $icon = 'volume-up';
                break;
            case 'OBJECT-IDENTITY':
                $icon = 'folder-empty';
                break;
            case 'AGENT-CAPABILITIES':
                $icon = 'lightbulb';
                break;
            default:
                $icon = 'spinner';
        }

        return Icon::create($icon, ['title' => $title]);
    }

    public function select($columns = [])
    {
        return $this->db()->select()
            ->from(['smn' => Node::TABLE], $columns)
            ->where('smn.mib_checksum = ?', $this->mib->get('mib_checksum'));
    }

    public function prepareQuery()
    {
        return $this->select([
            'smn.oid',
            'smn.object_name',
            'description' => 'SUBSTRING(smn.description, 1, 300)',
            'smn.macro',
            'smn.table_index',
            'smn.depth',
        ])->order('oid')->limit(500);
    }
}
