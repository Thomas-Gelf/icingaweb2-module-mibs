<?php

namespace Icinga\Module\Mibs\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Mibs\ActionController;
use Icinga\Module\Mibs\Web\Table\MibUploadsTable;
use Icinga\Module\Mibs\Web\Table\MissingImportsTable;
use Icinga\Module\Mibs\Web\Table\MissingMibsTable;

class ProcessingController extends ActionController
{
    public function init()
    {
        $this->getTabs()->activate($this->getRequest()->getActionName());
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->addTitle('Upload your MIB files');
        $this->actions()->add(
            Link::create($this->translate('Add'), 'mibs/mib/upload', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_next'
            ])
        );

        $this->showTable(
            new MibUploadsTable($this->db()),
            $this->translate('There are no pending MIB files in our queue')
        );
    }

    public function missingMibsAction()
    {
        $this->setAutorefreshInterval(30);
        $this->addTitle('Missing MIBs: unresolved imports');
        $this->showTable(new MissingMibsTable($this->db()), $this->translate('There are no unresolved imports'));
    }

    public function missingImportsAction()
    {
        $this->setAutorefreshInterval(30);
        $this->addTitle('Missing unresolved imports');
        $this->showTable(new MissingImportsTable($this->db()), $this->translate('There are no unresolved imports'));
    }

    protected function getTabs(): Tabs
    {
        // $uploads = count(new MibUploadsTable($this->db()));
        // $missingMibs = count(new MissingMibsTable($this->db()));
        // $missingImports = count(new MissingImportsTable($this->db()));
        $uploads = 0;
        $missingMibs = 0;
        $missingImports = 0;
        return $this->tabs()->add('index', [
            'title' => sprintf($this->translate('MIB Files (%d)'), $uploads),
            'url' => 'mibs/processing'
        ])->add('missing-mibs', [
            'title' => sprintf($this->translate('Missing MIBs (%d)'), $missingMibs),
            'url' => 'mibs/processing/missing-mibs'
        ])->add('missing-imports', [
            'title' => sprintf($this->translate('Missing Imports (%d)'), $missingImports),
            'url' => 'mibs/processing/missing-imports'
        ]);
    }
}
