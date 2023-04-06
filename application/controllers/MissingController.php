<?php

namespace Icinga\Module\Mibs\Controllers;

use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Mibs\ActionController;
use Icinga\Module\Mibs\Web\Table\MissingImportsTable;
use Icinga\Module\Mibs\Web\Table\MissingMibsTable;

class MissingController extends ActionController
{
    public function mibsAction()
    {
        $this->getTabs()->activate('mibs');
        $this->addTitle('Missing MIBs: unresolved imports');
        $this->showTable(new MissingMibsTable($this->db()), $this->translate('There are no unresolved imports'));
    }

    public function importsAction()
    {
        $this->getTabs()->activate('imports');
        $this->addTitle('Missing unresolved imports');
        $this->showTable(new MissingImportsTable($this->db()), $this->translate('There are no unresolved imports'));
    }

    protected function getTabs(): Tabs
    {
        $missingMibs = count(new MissingMibsTable($this->db()));
        $missingImports = count(new MissingImportsTable($this->db()));
        return $this->tabs()->add('mibs', [
            'title' => sprintf($this->translate('Missing MIBs (%d)'), $missingMibs),
            'url' => 'mibs/missing/mibs'
        ])->add('imports', [
            'title' => sprintf($this->translate('Missing Imports (%d)'), $missingImports),
            'url' => 'mibs/missing/imports'
        ]);
    }
}
