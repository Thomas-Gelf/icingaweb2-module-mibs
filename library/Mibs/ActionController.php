<?php

namespace Icinga\Module\Mibs;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Web\Widget\Hint;

abstract class ActionController extends CompatController
{
    /** @var Db */
    protected $db;

    protected function mainTabs(): Tabs
    {
        return $this->tabs()->add('dashboard', [
            'title' => $this->translate('Dashboard'),
            'url'   => 'mibs/dashboard'
        ])->add('organizations', [
            'title' => $this->translate('MIBs by Organization'),
            'url'   => 'mibs/organizations'
        ])->add('mibs', [
            'title' => $this->translate('All MIBs'),
            'url'   => 'mibs/mibs'
        ]);
    }

    protected function showTable(ZfQueryBasedTable $table, string $messageWhenEmpty)
    {
        $table->renderTo($this);
        if (count($table) === 0) {
            $this->content()->add(Hint::info(($messageWhenEmpty)));
        }
    }

    protected function db(): Db
    {
        if ($this->db === null) {
            $this->db = Db::fromResourceName($this->Config()->get('db', 'resource'));
        }

        return $this->db;
    }
}
