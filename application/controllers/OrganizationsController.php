<?php

namespace Icinga\Module\Mibs\Controllers;

use Icinga\Module\Mibs\ActionController;
use Icinga\Module\Mibs\Web\Table\MibOrganizationsTable;

class OrganizationsController extends ActionController
{
    public function indexAction()
    {
        $this->mainTabs()->activate('organizations');
        $this->addTitle($this->translate('SNMP MIBs by Organization'));
        $this->showTable(
            new MibOrganizationsTable($this->db()),
            $this->translate('There are no processed MIB files in this DB')
        );
    }
}
